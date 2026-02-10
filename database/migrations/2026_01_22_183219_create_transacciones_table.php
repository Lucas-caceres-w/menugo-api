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
        Schema::create('transacciones', function (Blueprint $table) {
            $table->id();
            $table->morphs('transaccionable');

            $table->decimal('total', 10, 2);
            $table->string('medio_pago');

            $table->string('payment_id')->unique();
            $table->string('estado')->default('pendiente');

            $table->string('referencia_externa')->nullable();
            $table->timestamp('fecha_pago')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transacciones');
    }
};
