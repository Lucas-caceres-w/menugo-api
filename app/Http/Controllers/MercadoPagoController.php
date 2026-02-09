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

    protected function handleSubscriptionPayment(array $payment, array $metadata)
    {
        $user = User::findOrFail($metadata['user_id']);
        $plan = $metadata['plan'];

        Transacciones::create([
            'user_id'   => $user->id,
            'payment_id' => $payment['id'],
            'status'    => $payment['status'],
            'amount'    => $payment['transaction_amount'] ?? 0,
            'medio_pago' => 'mercadopago',
            'fecha'     => now(),
        ]);

        if ($payment['status'] === 'approved') {
            Subscription::activatePlan($user, $plan);
        }
    }

    public function fetchPaymentByPlatform(string $paymentId): array
    {
        $accessToken = config('services.mercadopago.access_token');

        if (!$accessToken) {
            throw new \Exception('Access token de plataforma no configurado');
        }

        MercadoPagoConfig::setAccessToken($accessToken);

        $client = new \MercadoPago\Client\Payment\PaymentClient();

        $payment = $client->get($paymentId);

        return $payment->toArray();
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
        $type = $request->input('type');
        $data = $request->input('data');

        if ($type !== 'payment' || !isset($data['id'])) {
            return response()->json(['message' => 'Evento no relevante'], 200);
        }

        $paymentId = $data['id'];

        DB::beginTransaction();

        try {
            // 🔒 Idempotencia
            if (Transacciones::where('payment_id', $paymentId)->exists()) {
                DB::commit();
                return response()->json(['message' => 'Pago ya procesado'], 200);
            }

            // 🔍 Obtener pago desde MP
            $payment = $this->fetchPaymentByPlatform($paymentId);

            $metadata = $payment['metadata'] ?? [];
            $status   = $payment['status'] ?? 'unknown';

            if (!isset($metadata['type'])) {
                throw new \Exception('Pago sin metadata');
            }

            // 🧭 Router por tipo de pago
            match ($metadata['type']) {
                'pedido'       => $this->handlePedidoPayment($payment, $metadata),
                'subscription' => $this->handleSubscriptionPayment($payment, $metadata),
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

            if ($user->hasActiveSubscription()) {
                return response()->json([
                    'message' => 'Ya contas con una subscripcion activa'
                ], 400);
            }

            if (!$plan) {
                return response()->json([
                    'message' => 'Plan inválido'
                ], 400);
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
                    'title' => $planKey,
                    'quantity' => 1,
                    'unit_price' => (float) $plan['price'],
                    'currency_id' => 'ARS',
                ]],

                'payer' => [
                    'name'  => $user->name,
                    'email' => $user->email,
                ],

                'external_reference' => "subscription_{$user->id}_{$planKey}",

                'metadata' => [
                    'type'     => 'subscription',
                    'user_id'  => $user->id,
                    'plan'     => $planKey,
                ],

                'back_urls' => [
                    'success' => env('FRONTEND_URL') . '/dashboard/subscription?status=success',
                    'pending' => env('FRONTEND_URL') . '/dashboard/subscription?status=pending',
                    'failure' => env('FRONTEND_URL') . '/dashboard/subscription?status=failure',
                ],

                'auto_return' => 'approved',

                'notification_url' =>
                env('APP_URL') . '/api/webhooks/mercadopago',
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
