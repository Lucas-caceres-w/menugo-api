<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('preference_locals', function (Blueprint $table) {
            $table->id();
            $table->string('preference_id')->unique(); // o payment_id
            $table->foreignId('local_id')->constrained('locales')->cascadeOnDelete();
            $table->string('type'); // o payment_id
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('preference_locals');
    }
};
