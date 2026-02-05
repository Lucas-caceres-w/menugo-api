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
        $today = Carbon::now();
        $dayOfWeek = $today->dayOfWeek;
        $now = $today->format('H:i');

        // 1️⃣ Closure explícito del día (prioridad máxima)
        $closure = $this->closures()
            ->whereDate('date', $today->toDateString())
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

        // 3️⃣ Día marcado como cerrado en el schedule
        if ($schedule && (bool) $schedule->is_closed) {
            return [
                'open' => false,
                'reason' => 'closed_today',
                'schedule' => null,
            ];
        }

        // 4️⃣ No hay horario configurado
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

        $opens = Carbon::createFromFormat('H:i', $schedule->opens_at);
        $closes = Carbon::createFromFormat('H:i', $schedule->closes_at);
        $nowTime = Carbon::now();

        if ($opens->lt($closes)) {
            // Horario normal
            $isOpen = $nowTime->between($opens, $closes);
        } else {
            // Horario que cruza medianoche
            $isOpen = $nowTime->gte($opens) || $nowTime->lte($closes);
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
