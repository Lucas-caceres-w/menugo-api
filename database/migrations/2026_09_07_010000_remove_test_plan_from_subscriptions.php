<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('subscriptions')
            ->where('plan', 'test')
            ->update([
                'plan' => 'trial',
                'status' => 'cancelled',
                'ends_at' => now(),
            ]);

        DB::statement("ALTER TABLE subscriptions MODIFY plan ENUM('trial', 'basic', 'premium', 'full') NOT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE subscriptions MODIFY plan ENUM('trial', 'test', 'basic', 'premium', 'full') NOT NULL");
    }
};
