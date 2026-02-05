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

        // 2️⃣ Horario del día (usa isoWeekday para evitar errores)
        $schedule = $this->schedules()
            ->where('day_of_week', $now->isoWeekday())
            ->first();

        if (!$schedule || $schedule->is_closed) {
            return [
                'open' => false,
                'reason' => 'closed_today',
                'schedule' => null,
            ];
        }

        if (!$schedule->opens_at || !$schedule->closes_at) {
            return [
                'open' => false,
                'reason' => 'no_schedule',
                'schedule' => null,
            ];
        }

        // 3️⃣ Construcción EXPLÍCITA de fechas
        $opensAt = $now->copy()->setTimeFromTimeString($schedule->opens_at);
        $closesAt = $now->copy()->setTimeFromTimeString($schedule->closes_at);

        // 4️⃣ Si el cierre es menor o igual, cruza medianoche → sumar 1 día
        if ($closesAt->lte($opensAt)) {
            $closesAt->addDay();
        }

        // 5️⃣ Comparación REAL
        $isOpen = $now->between($opensAt, $closesAt);

        if (!$isOpen) {
            return [
                'open' => false,
                'reason' => 'out_of_hours',
                'schedule' => $schedule,
            ];
        }

        return [
            'open' => true,
            'reason' => 'open',
            'schedule' => $schedule,
        ];
    }
}
