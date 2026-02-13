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
        Schema::create('pedido_item_extras', function (Blueprint $table) {
            $table->id();

            // Relación con el item del pedido
            $table->foreignId('pedido_item_id')
                ->constrained('pedido_items')
                ->cascadeOnDelete();

            // Extra original (opcional, solo referencia)
            $table->foreignId('extra_id')
                ->nullable()
                ->constrained('producto_extras')
                ->nullOnDelete();

            // Snapshot del extra
            $table->string('extra_nombre');
            $table->integer('extra_precio'); // precio unitario del extra
            $table->integer('cantidad'); // cantidad seleccionada

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pedido_item_extras');
    }
};
