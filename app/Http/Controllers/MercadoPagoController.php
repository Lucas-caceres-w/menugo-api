<?php

namespace App\Http\Controllers;

use App\Models\Local;
use App\Models\MercadoPagoTokens;
use App\Models\Pedidos;
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
    protected function handlePedidoPayment(array $payment, array $metadata)
    {
        $pedido = Pedidos::findOrFail($metadata['pedido_id']);
        $local  = Local::findOrFail($metadata['local_id']);

        Transacciones::create([
            'pedido_id' => $pedido->id,
            'local_id'  => $local->id,
            'payment_id' => $payment['id'],
            'status'    => $payment['status'],
            'amount'    => $payment['transaction_amount'] ?? 0,
            'medio_pago' => $payment['payment_type_id'] ?? 'mercadopago',
            'fecha'     => now(),
        ]);

        if ($payment['status'] === 'approved') {
            $pedido->update(['estado' => 'pagado']);
        }

        if ($payment['status'] === 'rejected') {
            $pedido->update(['estado' => 'cancelado']);
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

        Transacciones::create([
            'user_id'   => $subscription->user_id,
            'payment_id' => $payment['id'],
            'status'    => $payment['status'],
            'amount'    => $payment['transaction_amount'] ?? 0,
            'medio_pago' => 'mercadopago',
            'fecha'     => now(),
        ]);

        // ✅ Activar solo si está aprobado
        if ($payment['status'] === 'approved') {
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

    public function fetchPaymentByPlatform(string $paymentId): array
    {
        $accessToken = env('MP_ACCESS_TOKEN');

        if (!$accessToken) {
            throw new \Exception('Access token de plataforma no configurado');
        }

        MercadoPagoConfig::setAccessToken($accessToken);

        $client = new \MercadoPago\Client\Payment\PaymentClient();
        $payment = $client->get($paymentId);

        return [
            'id' => $payment->id,
            'status' => $payment->status,
            'status_detail' => $payment->status_detail,
            'transaction_amount' => $payment->transaction_amount,
            'payment_type_id' => $payment->payment_type_id,
            'metadata' => (array) $payment->metadata,
            'external_reference' => $payment->external_reference,
            'payer' => [
                'email' => $payment->payer?->email,
                'id' => $payment->payer?->id,
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
                $validated['items']
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
                    'client_id'     => env('MP_CLIENT_ID'),
                    'client_secret' => env('MP_CLIENT_SECRET'),
                    'code'          => $code,
                    'grant_type'    => 'authorization_code',
                    'redirect_uri'  => env('MP_REDIRECT_URI'),
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
        logger('[MP WEBHOOK] Evento recibido', [
            'headers' => $request->headers->all(),
            'body' => $request->all(),
        ]);
        $type = $request->input('type');
        $data = $request->input('data');

        logger('[MP WEBHOOK] Tipo de evento', [
            'type' => $type,
            'data_id' => $data['id'] ?? null,
        ]);

        if ($type !== 'payment' || !isset($data['id'])) {
            return response()->json(['message' => 'Evento no relevante'], 200);
        }

        $paymentId = $data['id'];

        DB::beginTransaction();

        try {
            // 🔒 Idempotencia
            if (Transacciones::where('pedido_id', $paymentId)->exists()) {
                logger('[MP WEBHOOK] Pago duplicado (idempotencia)', [
                    'pedido_id' => $paymentId,
                ]);

                DB::commit();
                return response()->json(['message' => 'Pago ya procesado'], 200);
            }

            // 🔍 Obtener pago desde MP
            $payment = $this->fetchPaymentByPlatform($paymentId);

            logger('[MP WEBHOOK] Pago obtenido desde MP', [
                'payment_id' => $paymentId,
                'status' => $payment['status'] ?? null,
                'status_detail' => $payment['status_detail'] ?? null,
                'metadata' => $payment['metadata'] ?? [],
                'amount' => $payment['transaction_amount'] ?? null,
            ]);

            $metadata = $payment['metadata'] ?? [];
            $status = $payment['status'];
            $reference = $payment['external_reference'] ?? null;

            if ($status !== 'approved' || !$reference) {
                return response()->json(['ignored' => true], 200);
            }

            [$type, $id] = explode('_', $reference);

            if (!isset($metadata['type'])) {
                logger('Payment data', $payment);
            }

            // 🧭 Router por tipo de pago
            logger('[MP WEBHOOK] Routing por metadata', [
                'payment_id' => $paymentId,
                'type' => $metadata['type'] ?? null,
                'status' => $status,
            ]);

            match ($metadata['type']) {
                'pedido'       => $this->handlePedidoPayment($payment, $metadata),
                'subscription' => $this->handleSubscriptionPayment($payment),
                default        => throw new \Exception('Tipo de pago inválido'),
            };

            DB::commit();

            return response()->json(['message' => 'Webhook procesado']);
        } catch (\Throwable $e) {
            DB::rollBack();
            report($e);

            return response()->json([
                'message' => 'Error al procesar webhook',
            ], 500);
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

            if (!$plan) {
                return response()->json([
                    'message' => 'Plan inválido'
                ], 400);
            }

            $subscription = $user->activeSubscription();

            if ($subscription) {
                if ($plan['price'] <= $subscription->price) {
                    return response()->json([
                        'message' => 'Solo se permite subir de plan'
                    ], 400);
                }

                $amount = $this->calculateUpgradeAmount($subscription, $plan);
            } else {
                $amount = $plan['price'];
            }


            // 🔐 Token FIJO de la plataforma
            MercadoPagoConfig::setAccessToken(
                env('MP_ACCESS_TOKEN')
            );

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

                'external_reference' => "subscription_{$user->id}_{$planKey}",

                'metadata' => [
                    'type'            => 'subscription',
                    'action'          => $subscription ? 'upgrade' : 'new',
                    'user_id'         => $user->id,
                    'from_plan'       => $subscription?->plan,
                    'to_plan'         => $planKey,
                    'original_amount' => $plan['price'],
                    'charged_amount'  => $amount,
                ],

                'back_urls' => [
                    'success' => env('FRONTEND_URL') . '/dashboard/subscription?status=success',
                    'pending' => env('FRONTEND_URL') . '/dashboard/subscription?status=pending',
                    'failure' => env('FRONTEND_URL') . '/dashboard/subscription?status=failure',
                ],

                'auto_return' => 'approved',

                'notification_url' =>
                env('APP_URL') . '/api/mercadopago/webhook',
            ]);

            return response()->json([
                'checkout_url' => $preference->init_point,
            ]);
        } catch (MPApiException $e) {
            // 🔥 Error propio de MercadoPago
            logger('MercadoPago API error', [
                'status' => $e->getApiResponse()?->getStatusCode(),
                'body'   => $e->getApiResponse()?->getContent(),
            ]);

            return response()->json([
                'message' => 'Error al comunicarse con MercadoPago',
                'code' => 'MP_API_ERROR'
            ], 502);
        } catch (\Throwable $e) {
            // 🔥 Error inesperado
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
}
