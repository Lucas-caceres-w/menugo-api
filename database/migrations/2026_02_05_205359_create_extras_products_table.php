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
        Schema::create('producto_extras', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')
                ->constrained('productos')
                ->cascadeOnDelete();
            $table->string('nombre'); // +Queso, +Papas, +Salsa
            $table->integer('precio'); // precio por unidad
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('producto_extras');
    }
};
