<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class Subscription extends Model
{
    protected $fillable = [
        'user_id',
        'plan',
        'starts_at',
        'ends_at',
        'price',
        'currency',
        'status',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }


    public function isActive(): bool
    {
        return
            $this->status === 'active' &&
            (!$this->ends_at || now()->lte($this->ends_at));
    }

    public function activatePlan(User $user, string $planKey): void
    {
        $planConfig = config("plans.$planKey");

        if (!$planConfig) {
            throw new \Exception('Plan inválido');
        }

        $now = Carbon::now();

        $this->update([
            'user_id'      => $user->id,
            'plan'         => $planKey,
            'status'       => 'active',
            'starts_at'    => $now,
            'ends_at'      => $now->copy()->addDays($planConfig['duration_days'] ?? 30),
            'price'        => $planConfig->price,
            'currency'     => 'ARS'
        ]);
    }


    public static function createTrialForUser(User $user)
    {
        $plan = config('plans.trial');

        return self::create([
            'user_id'   => $user->id,
            'plan'      => 'trial',
            'starts_at' => now(),
            'ends_at'   => now()->addDays($plan['duration_days']),
            'price'     => 0,
            'status'    => 'active',
        ]);
    }
}
