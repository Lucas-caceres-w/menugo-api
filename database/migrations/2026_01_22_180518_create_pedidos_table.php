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
        Schema::create('pedidos', function (Blueprint $table) {
            $table->id();

            $table->foreignId('local_id')->constrained('locales')->cascadeOnDelete();
            $table->decimal('total', 10, 2);
            $table->string('client_name');
            $table->string('client_phone');
            $table->string('client_address');
            $table->string('observacion')->nullable();
            $table->string('tipo_entrega')->default('delivery');
            $table->string('payment_method');
            $table->string('payment_status');
            $table->string('estado')->default('pendiente');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pedidos');
    }
};
