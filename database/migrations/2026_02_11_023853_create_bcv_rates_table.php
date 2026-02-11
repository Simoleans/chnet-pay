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
        Schema::create('bcv_rates', function (Blueprint $table) {
            $table->id();
            $table->decimal('rate', 10, 2); // Precio del BCV (ej: 56.50)
            $table->date('date'); // Fecha de la tasa
            $table->unsignedBigInteger('updated_by')->nullable(); // Usuario que actualizó
            $table->timestamps();

            // Índice para consultar rápidamente la última tasa
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bcv_rates');
    }
};
