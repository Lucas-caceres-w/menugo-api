<?php

namespace App\Http\Controllers;

use App\Models\Local;
use App\Models\MercadoPagoTokens;
use App\Models\Pedidos;
use App\Models\PreferenceLocal;
use App\Models\Subscription;
use App\Models\Transacciones;
use App\Models\User;
use App\Services\MercadoPagoTokensService;
use App\Services\MercadoPagoServices;
use Carbon\Carbon;
use Illuminate\Container\Attributes\Log;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use MercadoPago\Client\Preference\PreferenceClient;
use MercadoPago\Exceptions\MPApiException;
use MercadoPago\MercadoPagoConfig;

class MercadoPagoController extends Controller
{
    protected $mpService;
    protected function handlePedidoPayment(array $payment)
    {
        $reference = $payment['external_reference'] ?? null;

        if (!$reference) {
            return;
        }

        [$type, $pedidoId] = explode('_', $reference);

        $pedido = Pedidos::find($pedidoId);

        if (!$pedido) {
            logger('[MP PEDIDO] Pedido no encontrado', [
                'pedido_id' => $pedidoId,
            ]);
            return;
        }

        // ✅ Idempotencia: si ya existe la transacción, no hacer nada
        if ($pedido->transacciones()->where('payment_id', $payment['id'])->exists()) {
            return;
        }

        $pedido->transacciones()->create([
            'total' => $payment['transaction_amount'],
            'medio_pago' => 'mercadopago',
            'payment_id' => $payment['id'],
            'estado' => $payment['status'],
            'referencia_externa' => $reference,
            'fecha_pago' => $payment['status'] === 'approved' ? now() : null,
        ]);

        if ($payment['status'] === 'approved') {

            $pedido->update([
                'payment_status' => 'approved'
            ]);
        }

        if ($payment['status'] === 'rejected') {
            $pedido->update([
                'payment_status' => 'rejected'
            ]);
        }
    }

    protected function handleSubscriptionPayment(array $payment)
    {
        $reference = $payment['external_reference'] ?? null;

        if (!$reference) {
            logger('Pago sin external_reference', [
                'payment_id' => $payment['id'],
            ]);
            return;
        }

        [$type, $subscriptionId] = explode('_', $reference);

        if ($type !== 'subscription') {
            return;
        }

        $subscription = Subscription::find($subscriptionId);

        if (!$subscription) {
            logger('Suscripción no encontrada', [
                'subscription_id' => $subscriptionId,
                'payment_id' => $payment['id'],
            ]);
            return;
        }

        if ($subscription->transacciones()->where('payment_id', $payment['id'])->exists()) {
            return;
        }

        $subscription->transacciones()->create([
            'total' => $payment['transaction_amount'],
            'medio_pago' => 'mercadopago',
            'payment_id' => $payment['id'],
            'estado' => $payment['status'],
            'referencia_externa' => $payment['external_reference'] ?? null,
            'fecha_pago' => $payment['status'] === 'approved' ? now() : null,
        ]);

        // ✅ Activar solo si está aprobado
        if ($payment['status'] === 'approved') {
            Subscription::where('user_id', $subscription->user_id)
                ->where('id', '!=', $subscription->id)
                ->where('status', 'active')
                ->update([
                    'status' => 'ended',
                    'ends_at' => now(),
                ]);

            $subscription->activate();
        }
    }

    private function calculateUpgradeAmount($subscription, array $newPlan)
    {
        $now = Carbon::now();

        $totalDays = Carbon::parse($subscription->started_at)
            ->diffInDays(Carbon::parse($subscription->ends_at));

        $remainingDays = $now->diffInDays($subscription->ends_at, false);

        if ($remainingDays <= 0) {
            return $newPlan['price'];
        }

        $currentPlan = config("plans.{$subscription->plan}");

        $currentDaily = $currentPlan['price'] / $totalDays;
        $newDaily     = $newPlan['price'] / $totalDays;

        $differencePerDay = $newDaily - $currentDaily;

        $amount = max(0, round($differencePerDay * $remainingDays, 2));

        return $amount;
    }

    public function fetchPaymentByPlatform(string $paymentId, string $accessToken): array
    {
        if (!$accessToken) {
            throw new \Exception('Access token no configurado');
        }

        MercadoPagoConfig::setAccessToken($accessToken);

        $client = new \MercadoPago\Client\Payment\PaymentClient();
        $payment = $client->get($paymentId);

        // 🔥 Convertir a array seguro
        $data = json_decode(json_encode($payment), true);

        return [
            'id' => $data['id'] ?? null,
            'status' => $data['status'] ?? null,
            'status_detail' => $data['status_detail'] ?? null,
            'transaction_amount' => $data['transaction_amount'] ?? null,
            'payment_type_id' => $data['payment_type_id'] ?? null,
            'metadata' => $data['metadata'] ?? [],
            'external_reference' => $data['external_reference'] ?? null,
            'payer' => [
                'email' => $data['payer']['email'] ?? null,
                'id' => $data['payer']['id'] ?? null,
            ],
        ];
    }

    public function __construct(MercadoPagoServices $mpService)
    {
        $this->mpService = $mpService;
    }

    /**
     * Crear preferencia de pago para un pedido
     */
    public function createPreference(Request $request)
    {
        $validated = $request->validate([
            'local_id' => 'required|integer|exists:locales,id',
            'pedido_id' => 'required|integer|exists:pedidos,id',
            'items' => 'required|array',
            'payer' => 'required|array',
            'amount' => 'required|integer'
        ]);

        $local = Local::findOrFail($validated['local_id']);
        $pedido = Pedidos::findOrFail($validated['pedido_id']);

        try {
            $preference = $this->mpService->createPreference(
                $local,
                $pedido
            );

            return response()->json([
                'success' => true,
                'init_point' => $preference->init_point
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al crear preferencia',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Callback OAuth: vincular local
     */
    public function oauth(Request $request)
    {
        $code = $request->input('code');
        $local_id = $request->input('local_id');

        if (!$code || !$local_id) {
            return response()->json(['message' => 'Faltan parámetros'], 400);
        }

        $local = Local::findOrFail($local_id);

        try {
            $response = Http::asForm()->post(
                'https://api.mercadopago.com/oauth/token',
                [
                    'client_id'     => config('services.mercadopago.client_id'),
                    'client_secret' => config('services.mercadopago.client_secret'),
                    'code'          => $code,
                    'grant_type'    => 'authorization_code',
                    'redirect_uri'  => config('services.mercadopago.redirect_uri'),
                ]
            );

            if ($response->failed()) {
                return response()->json([
                    'error' => 'Error al obtener el token',
                    'details' => $response->json(),
                ], $response->status());
            }

            $this->mpService->saveToken($local, $response->json());

            return response()->json(['success' => true, 'message' => 'MercadoPago vinculado correctamente']);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Error OAuth', 'details' => $e->getMessage()], 500);
        }
    }

    /**
     * Webhook de pago
     */
    public function webhook(Request $request)
    {
        logger('[MP WEBHOOK] Evento recibido', $request->all());

        // 🔎 Detectar tipo de evento
        $type = $request->input('type')
            ?? $request->input('topic')
            ?? ($request->input('action') ? explode('.', $request->input('action'))[0] : null);

        // 🔎 Detectar payment id
        $paymentId = $request->input('data.id')
            ?? $request->input('data_id')
            ?? $request->input('id');

        logger('[MP WEBHOOK] Detectado', ['type' => $type, 'payment_id' => $paymentId]);

        if ($type !== 'payment' || !$paymentId) {
            logger('[MP WEBHOOK] Evento ignorado');
            return response()->json(['ignored' => true], 200);
        }

        DB::beginTransaction();

        try {
            // 🔒 Idempotencia
            $transaccion = Transacciones::where('payment_id', $paymentId)->first();
            if ($transaccion) {
                DB::commit();
                return response()->json(['message' => 'Pago ya procesado'], 200);
            }

            // Mercado Pago incluye el usuario vendedor en la notificación.
            // Los pedidos usan el token OAuth del local; las suscripciones usan el token de plataforma.
            $localToken = MercadoPagoTokens::query()
                ->where('mercadopago_user_id', (string) $request->input('user_id'))
                ->first();

            $token = $localToken
                ? $this->mpService->getValidAccessToken($localToken->local)
                : config('services.mercadopago.access_token');

            logger('[MP WEBHOOK] Token seleccionado', [
                'source' => $localToken ? 'local_oauth' : 'platform',
                'mp_user_id' => $request->input('user_id'),
            ]);

            // 🔍 Consultar el pago completo en MercadoPago
            $payment = $this->fetchPaymentByPlatform($paymentId, $token);

            $reference = $payment['external_reference'] ?? null;

            if (!$reference || !str_contains($reference, '_')) {
                DB::commit();
                return response()->json(['ignored' => true], 200);
            }

            [$tipo, $id] = explode('_', $reference);

            // 🔎 Routing por tipo
            match ($tipo) {
                'pedido'       => $this->handlePedidoPayment($payment),
                'subscription' => $this->handleSubscriptionPayment($payment),
                default        => throw new \Exception('Tipo de pago inválido: ' . $tipo),
            };

            DB::commit();
            return response()->json(['message' => 'Webhook procesado']);
        } catch (MPApiException $e) {
            DB::rollBack();

            $apiResponse = $e->getApiResponse();
            logger('[MP WEBHOOK API ERROR]', [
                'payment_id' => $paymentId,
                'status' => $apiResponse?->getStatusCode(),
                'body' => $apiResponse?->getContent(),
            ]);

            report($e);
            return response()->json(['message' => 'Mercado Pago rechazó la consulta del pago'], 502);
        } catch (\Throwable $e) {
            DB::rollBack();
            logger('[MP WEBHOOK ERROR]', ['error' => $e->getMessage(), 'payment_id' => $paymentId]);
            report($e);
            return response()->json(['message' => 'Error al procesar webhook'], 500);
        }
    }
    /**
     * Guardar transacción manual (por pago externo)
     */
    public function storeTransaction(Request $request)
    {
        $validated = $request->validate([
            'pedido_id' => 'required|integer|exists:pedidos,id',
            'local_id' => 'required|integer|exists:locales,id',
            'amount' => 'required|numeric',
            'status' => 'required|string',
            'medio_pago' => 'required|string',
            'fecha' => 'nullable|date'
        ]);

        $transaction = Transacciones::create($validated);

        // Actualizar estado del pedido si corresponde
        $pedido = Pedidos::findOrFail($validated['pedido_id']);
        if ($validated['status'] === 'pagado') {
            $pedido->update(['estado' => 'pagado']);
        } elseif ($validated['status'] === 'cancelado') {
            $pedido->update(['estado' => 'cancelado']);
        }

        return response()->json(['message' => 'Transacción registrada', 'transaction' => $transaction]);
    }

    public function disconnect(int $localId)
    {
        MercadoPagoTokens::where('local_id', $localId)->delete();

        return response()->json([
            'success' => true,
            'message' => 'Cuenta de Mercado Pago desvinculada',
        ]);
    }

    /**
     * Estado de vinlacion del cliente
     */
    public function settings(int $localId, MercadoPagoTokensService $mpTokenService)
    {
        $local = Local::findOrFail($localId);

        return response()->json([
            'mercado_pago' => $mpTokenService->getStatus(
                $local->mercadoPagoToken
            ),
        ]);
    }
    
    public function iniciarSubscripcion(Request $request)
    {
        try {
            $user = auth()->user();
            $planKey = $request->input('plan');
            $plan = config("plans.$planKey");

            if (!config('services.mercadopago.client_id') ||
                !config('services.mercadopago.client_secret') ||
                !config('services.mercadopago.access_token')) {
                return response()->json([
                    'message' => 'Mercado Pago no está configurado en el servidor',
                    'code' => 'MP_NOT_CONFIGURED',
                ], 503);
            }

            if (!$plan) {
                return response()->json([
                    'message' => 'Plan inválido'
                ], 400);
            }

            // Suscripción activa actual
            $activeSubscription = $user->activeSubscription();

            if ($activeSubscription && $planKey === $activeSubscription->plan) {
                return response()->json([
                    'message' => 'Ya tienes este plan activo'
                ], 400);
            }

            if ($activeSubscription && $plan['price'] <= $activeSubscription->price) {
                return response()->json([
                    'message' => 'Solo puedes subir a un plan de mayor valor',
                ], 400);
            }

            // Calcular monto a cobrar
            $amount = $activeSubscription
                ? $this->calculateUpgradeAmount($activeSubscription, $plan)
                : $plan['price'];

            // Crear nueva suscripción pendiente
            $newSubscription = Subscription::create([
                'user_id' => $user->id,
                'plan' => $planKey,
                'status' => 'pending',
                'price' => $plan['price'],
            ]);

            // 🔐 Configurar MercadoPago
            MercadoPagoConfig::setAccessToken(config('services.mercadopago.access_token'));
            MercadoPagoConfig::setRuntimeEnviroment(
                app()->environment('production')
                    ? MercadoPagoConfig::SERVER
                    : MercadoPagoConfig::LOCAL
            );

            $client = new PreferenceClient();

            $preference = $client->create([
                'items' => [[
                    'title' => "Plan {$planKey}",
                    'quantity' => 1,
                    'unit_price' => (float) $amount,
                    'currency_id' => 'ARS',
                ]],
                'payer' => [
                    'name'  => $user->name,
                    'email' => $user->email,
                ],
                'external_reference' => "subscription_{$newSubscription->id}_{$planKey}",
                'metadata' => [
                    'type' => 'subscription',
                    'action' => $activeSubscription ? 'upgrade' : 'new',
                    'user_id' => $user->id,
                    'from_plan' => $activeSubscription?->plan,
                    'to_plan' => $planKey,
                    'original_amount' => $plan['price'],
                    'charged_amount' => $amount,
                ],
                'back_urls' => [
                    'success' => config('app.frontend_url') . '/dashboard/subscription?status=success',
                    'pending' => config('app.frontend_url') . '/dashboard/subscription?status=pending',
                    'failure' => config('app.frontend_url') . '/dashboard/subscription?status=failure',
                ],
                'auto_return' => 'approved',
                'notification_url' => config('services.mercadopago.webhook_url')
                    ?: rtrim(config('app.url'), '/') . '/api/mercadopago/webhook',
            ]);

            return response()->json([
                'checkout_url' => $preference->init_point,
            ]);
        } catch (MPApiException $e) {
            logger('MercadoPago API error', [
                'status' => $e->getApiResponse()?->getStatusCode(),
                'body'   => $e->getApiResponse()?->getContent(),
            ]);

            return response()->json([
                'message' => 'Error al comunicarse con MercadoPago',
                'code' => 'MP_API_ERROR'
            ], 502);
        } catch (\Throwable $e) {
            logger('Error iniciarSubscripcion', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Error interno del servidor',
                'code' => 'INTERNAL_ERROR'
            ], 500);
        }
    }

    public function renewSubscription(Request $request)
    {
        $subscription = $request->user()->activeSubscription()
            ?: $request->user()->subscription()
                ->where('status', '!=', 'pending')
                ->latest('id')
                ->first();

        if (!$subscription || !$subscription->isRenewalAvailable()) {
            return response()->json([
                'message' => 'La renovación estará disponible tres días antes del vencimiento y durante los tres días posteriores',
            ], 409);
        }

        $plan = config("plans.{$subscription->plan}");
        if (!$plan) {
            return response()->json(['message' => 'Plan inválido'], 400);
        }

        $newSubscription = Subscription::create([
            'user_id' => $request->user()->id,
            'plan' => $subscription->plan,
            'status' => 'pending',
            'price' => $plan['price'],
            'currency' => 'ARS',
        ]);

        MercadoPagoConfig::setAccessToken(config('services.mercadopago.access_token'));
        MercadoPagoConfig::setRuntimeEnviroment(
            app()->environment('production')
                ? MercadoPagoConfig::SERVER
                : MercadoPagoConfig::LOCAL
        );

        try {
            $preference = (new PreferenceClient())->create([
                'items' => [[
                    'title' => "Renovación del plan {$subscription->plan}",
                    'quantity' => 1,
                    'unit_price' => (float) $plan['price'],
                    'currency_id' => 'ARS',
                ]],
                'payer' => [
                    'name' => $request->user()->name,
                    'email' => $request->user()->email,
                ],
                'external_reference' => "subscription_{$newSubscription->id}_{$subscription->plan}",
                'back_urls' => [
                    'success' => config('app.frontend_url') . '/dashboard/subscription?status=success',
                    'pending' => config('app.frontend_url') . '/dashboard/subscription?status=pending',
                    'failure' => config('app.frontend_url') . '/dashboard/subscription?status=failure',
                ],
                'auto_return' => 'approved',
                'notification_url' => config('services.mercadopago.webhook_url')
                    ?: rtrim(config('app.url'), '/') . '/api/mercadopago/webhook',
            ]);

            return response()->json(['checkout_url' => $preference->init_point]);
        } catch (\Throwable $e) {
            $newSubscription->delete();
            report($e);

            return response()->json([
                'message' => 'No se pudo crear el checkout de renovación',
            ], 502);
        }
    }
}
