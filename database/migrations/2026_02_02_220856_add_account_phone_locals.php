<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('locales', function (Blueprint $table) {
            $table->string('phone', 30)
                  ->nullable()
                  ->after('nombre');

            // Alias / CBU / CVU
            $table->string('account', 100)
                  ->nullable()
                  ->after('phone');
        });
    }

    public function down(): void
    {
        Schema::table('locales', function (Blueprint $table) {
            $table->dropColumn(['phone', 'account']);
        });
    }
};
