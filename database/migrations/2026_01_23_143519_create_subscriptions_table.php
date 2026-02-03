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
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->enum('plan', ['trial', 'basic', 'premium', 'full']); // trial | basic | premium | full
            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->nullable();
            $table->integer('price')->nullable();
            $table->string('currency')->default('ARS');
            $table->string('status')->default('active');
            // active | expired | canceled

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
