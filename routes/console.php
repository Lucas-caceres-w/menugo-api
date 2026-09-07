<?php

use Illuminate\Foundation\Inspiring;
use App\Models\Subscription;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

app(Schedule::class)->call(function (): void {
    Subscription::query()
        ->with('user')
        ->where('status', 'active')
        ->whereNotNull('ends_at')
        ->where('ends_at', '<=', now()->addDays(3))
        ->whereNull('renewal_notified_at')
        ->each(function (Subscription $subscription): void {
            $subscription->user->notify(new \App\Notifications\SubscriptionRenewalDue($subscription));
            $subscription->update(['renewal_notified_at' => now()]);
        });

    Subscription::query()
        ->where('status', 'active')
        ->whereNotNull('ends_at')
        ->where('ends_at', '<', now()->subDays(3))
        ->update(['status' => 'expired']);
})->dailyAt('03:00');
