<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table): void {
            $table->boolean('auto_renew')->default(false)->after('status');
            $table->string('preapproval_id')->nullable()->after('auto_renew');
            $table->timestamp('renewal_notified_at')->nullable()->after('preapproval_id');
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table): void {
            $table->dropColumn(['auto_renew', 'preapproval_id', 'renewal_notified_at']);
        });
    }
};
