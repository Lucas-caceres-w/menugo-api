<?php

namespace App\Services;

use App\Models\MercadoPagoTokens;
use Carbon\Carbon;

class MercadoPagoTokensService
{
            public function getStatus(?MercadoPagoTokens $token): array
            {
                        if (!$token) {
                                    return [
                                                'connected' => false,
                                                'status' => 'not_connected',
                                                'expires_at' => null,
                                                'has_refresh' => false,
                                                'requires_relink' => true,
                                    ];
                        }

                        $now = now();
                        $expiresAt = $token->expires_at;
                        $hasRefresh = !empty($token->getRawOriginal('refresh_token'));

                        // 🔁 Tiene refresh → siempre conectado
                        if ($hasRefresh) {
                                    return [
                                                'connected' => true,
                                                'status' => 'active',
                                                'expires_at' => null,
                                                'has_refresh' => true,
                                                'requires_relink' => false,
                                    ];
                        }

                        // ⏳ No tiene refresh → acceso temporal
                        if ($expiresAt && $expiresAt->isPast()) {
                                    return [
                                                'connected' => false,
                                                'status' => 'expired',
                                                'expires_at' => $expiresAt,
                                                'has_refresh' => false,
                                                'requires_relink' => true,
                                    ];
                        }

                        return [
                                    'connected' => true,
                                    'status' => $expiresAt && $expiresAt->diffInHours($now) < 24
                                                ? 'expiring_soon'
                                                : 'temporary',
                                    'expires_at' => $expiresAt,
                                    'has_refresh' => false,
                                    'requires_relink' => true,
                        ];
            }

            public function expiresInSeconds(MercadoPagoTokens $token): int
            {
                        return max(0, now()->diffInSeconds($token->expires_at, false));
            }
}
