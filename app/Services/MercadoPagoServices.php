<?php

namespace App\Services;

use App\Models\Local;
use App\Models\MercadoPagoTokens;
use App\Models\Pedidos;
use App\Models\PreferenceLocal;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;
use Exception;

class MercadoPagoServices
{
            /**
             * Obtener un access_token válido para un local
             */
            public function getValidAccessToken(Local $local)
            {
                        $token = $local->mercadoPagoToken;

                        if (!$token) {
                                    throw new Exception('Local no tiene MercadoPago vinculado.');
                        }

                        // Verifica expiración
                        if (
                                    $token->expires_at &&
                                    Carbon::parse($token->expires_at)->subMinutes(5)->lt(now())
                        ) {
                                    return $this->refreshToken($local);
                        }


                        return $token->access_token;
            }

            /**
             * Refrescar token expirado
             */
            public function refreshToken(Local $local)
            {
                        $token = $local->mercadoPagoToken;

                        if (!$token) {
                                    throw new Exception('Local no tiene MercadoPago vinculado.');
                        }

                        $response = Http::asForm()->post('https://api.mercadopago.com/oauth/token', [
                                    'grant_type'    => 'refresh_token',
                                    'client_id'     => env('MP_CLIENT_ID'),
                                    'client_secret' => env('MP_CLIENT_SECRET'),
                                    'refresh_token' => $token->refresh_token,
                        ]);

                        if ($response->failed()) {
                                    throw new Exception('No se pudo refrescar el token: ' . json_encode($response->json()));
                        }

                        $data = $response->json();

                        // Guardar nuevo token en DB
                        $token->update([
                                    'access_token'  => $data['access_token'],
                                    'refresh_token' => $data['refresh_token'] ?? $token->refresh_token,
                                    'expires_at'    => now()->addSeconds($data['expires_in'] ?? 21600), // default 6h
                        ]);

                        return $token->access_token;
            }

            /**
             * Guardar token inicial tras OAuth
             */
            public function saveToken(Local $local, array $data)
            {
                        $token = MercadoPagoTokens::updateOrCreate(
                                    ['local_id' => $local->id],
                                    [
                                                'access_token'  => $data['access_token'],
                                                'refresh_token' => $data['refresh_token'] ?? null,
                                                'expires_at'    => now()->addSeconds($data['expires_in'] ?? 21600),
                                                'mp_user_id'    => $data['user_id'] ?? null,
                                    ]
                        );

                        return $token;
            }

            /**
             * Crear preferencia de pago para un pedido
             */
            public function createPreference(Local $local, Pedidos $pedido)
            {
                        try {
                                    $accessToken = $this->getValidAccessToken($local);

                                    \MercadoPago\MercadoPagoConfig::setAccessToken($accessToken);
                                    \MercadoPago\MercadoPagoConfig::setRuntimeEnviroment(
                                                app()->environment('production')
                                                            ? \MercadoPago\MercadoPagoConfig::SERVER
                                                            : \MercadoPago\MercadoPagoConfig::LOCAL
                                    );

                                    $client = new \MercadoPago\Client\Preference\PreferenceClient();

                                    $items = $pedido->items->map(function ($item) {

                                                $unitPrice = $item->subtotal / $item->cantidad;

                                                return [
                                                            'title'       => $item->producto_nombre,
                                                            'quantity'    => $item->cantidad,
                                                            'unit_price'  => (float) $unitPrice,
                                                            'currency_id' => 'ARS',
                                                ];
                                    })->toArray();

                                    if ($pedido->costo_envio > 0) {
                                                $items[] = [
                                                            'title'       => 'Costo de envío',
                                                            'quantity'    => 1,
                                                            'unit_price'  => (float) $pedido->costo_envio,
                                                            'currency_id' => 'ARS',
                                                ];
                                    }

                                    logger()->info('MP back_urls', [
                                                'success' => env('FRONTEND_URL') . '/pedido/success?id=' . $pedido->id,
                                    ]);


                                    $preference = $client->create([
                                                'items' => $items,

                                                'payer' => [
                                                            'name' => $pedido->client_name,
                                                            'phone' => [
                                                                        'area_code' => '54', // Argentina
                                                                        'number'    => preg_replace('/\D/', '', $pedido->client_phone),
                                                            ],
                                                            'address' => [
                                                                        'street_name' => $pedido->client_address,
                                                            ],
                                                ],

                                                'metadata' => [
                                                            'type' => 'pedido',
                                                            'pedido_id' => $pedido->id,
                                                            'local_id'  => $local->id,
                                                ],

                                                'external_reference' => "pedido_{$pedido->id}",

                                                'back_urls' => [
                                                            'success' => env('FRONTEND_URL') . '/pedido/success?id=' . $pedido->id,
                                                            'failure' => env('FRONTEND_URL') . '/pedido/failure?id=' . $pedido->id,
                                                            'pending' => env('FRONTEND_URL') . '/pedido/pending?id=' . $pedido->id,
                                                ],

                                                'notification_url' => env('APP_URL') . '/api/mercadopago/webhook',

                                                'auto_return' => 'approved',

                                                'statement_descriptor' => 'MenuGo',
                                    ]);

                                    PreferenceLocal::create([
                                                'preference_id' => $preference->id, // o $preference->init_point si querés
                                                'local_id'      => $local->id,
                                                'type'          => 'pedido'
                                    ]);

                                    return $preference;
                        } catch (\MercadoPago\Exceptions\MPApiException $e) {
                                    logger()->error('MercadoPago API error', [
                                                'status' => $e->getApiResponse()?->getStatusCode(),
                                                'body'   => $e->getApiResponse()?->getContent(),
                                    ]);
                                    throw $e;
                        }
            }

            /**
             * Consultar pago por ID
             */
            public function fetchPayment(Local $local, string $paymentId)
            {
                        $accessToken = $this->getValidAccessToken($local);

                        $response = Http::withToken($accessToken)
                                    ->get("https://api.mercadopago.com/v1/payments/{$paymentId}");

                        if ($response->unauthorized()) {
                                    $accessToken = $this->refreshToken($local);
                                    $response = Http::withToken($accessToken)
                                                ->get("https://api.mercadopago.com/v1/payments/{$paymentId}");
                        }

                        if ($response->failed()) {
                                    throw new Exception('No se pudo obtener el pago: ' . json_encode($response->json()));
                        }

                        return $response->json();
            }
}
