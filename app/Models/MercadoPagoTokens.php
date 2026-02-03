<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class MercadoPagoTokens extends Model
{
    protected $table = 'mercado_pago_tokens';

    protected $fillable = [
        'local_id',
        'access_token',
        'refresh_token',
        'expires_at',
        'mercadopago_user_id',
        'scope',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    /**
     * Encriptar tokens automáticamente
     */
    public function setAccessTokenAttribute($value)
    {
        $this->attributes['access_token'] = Crypt::encryptString($value);
    }

    public function getAccessTokenAttribute($value)
    {
        return Crypt::decryptString($value);
    }

    public function setRefreshTokenAttribute($value)
    {
        $this->attributes['refresh_token'] = Crypt::encryptString($value);
    }

    public function getRefreshTokenAttribute($value)
    {
        if (!$value) {
            return null;
        }

        try {
            return Crypt::decryptString($value);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Relación
     */
    public function local()
    {
        return $this->belongsTo(Local::class);
    }
}
