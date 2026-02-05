<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class Local extends Model
{
    protected $table = 'locales';
    protected $fillable = ['user_id', 'nombre', 'direccion', 'descripcion', 'slug', 'phone', 'account'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
    public function categorias()
    {
        return $this->hasMany(Categorias::class);
    }
    public function pedidos()
    {
        return $this->hasMany(Categorias::class);
    }
    public function facturacion()
    {
        return $this->hasMany(Categorias::class);
    }
    // Local.php
    public function mercadoPagoToken()
    {
        return $this->hasOne(MercadoPagoTokens::class);
    }
    public function mp_active()
    {
        return $this->hasOne(MercadoPagoTokens::class)
            ->where(function ($q) {
                $q->whereNotNull('refresh_token')
                    ->orWhere('expires_at', '>', now());
            });
    }

    public function schedules()
    {
        return $this->hasMany(localSchedules::class);
    }

    public function closures()
    {
        return $this->hasMany(localClosures::class);
    }
    public function todaySchedule()
    {
        $now = Carbon::now();
        $dayOfWeek = $now->dayOfWeek;

        // 1️⃣ Closure explícito del día
        $closure = $this->closures()
            ->whereDate('date', $now->toDateString())
            ->first();

        if ($closure) {
            return [
                'open' => false,
                'reason' => $closure->reason ?? 'closed_today',
                'schedule' => null,
            ];
        }

        // 2️⃣ Horario del día
        $schedule = $this->schedules()
            ->where('day_of_week', $dayOfWeek)
            ->first();

        // 3️⃣ Día cerrado manualmente
        if ($schedule && (bool) $schedule->is_closed) {
            return [
                'open' => false,
                'reason' => 'closed_today',
                'schedule' => null,
            ];
        }

        // 4️⃣ No hay horario
        if (
            !$schedule ||
            !$schedule->opens_at ||
            !$schedule->closes_at
        ) {
            return [
                'open' => false,
                'reason' => 'no_schedule',
                'schedule' => null,
            ];
        }

        $nowTime = $now->format('H:i:s');
        $opens   = $schedule->opens_at;
        $closes  = $schedule->closes_at;

        if ($opens === $closes) {
            $isOpen = true; // 24hs
        } elseif ($opens < $closes) {
            // NO cruza medianoche
            $isOpen = $nowTime >= $opens && $nowTime < $closes;
        } else {
            // Cruza medianoche
            $isOpen = $nowTime >= $opens || $nowTime < $closes;
        }

        if (!$isOpen) {
            return [
                'open' => false,
                'reason' => 'out_of_hours',
                'schedule' => $schedule,
            ];
        }

        // ✅ Abierto
        return [
            'open' => true,
            'reason' => 'open',
            'schedule' => $schedule,
        ];
    }
}
