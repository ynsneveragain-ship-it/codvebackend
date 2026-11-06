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
     Schema::create('suscripciones', function (Blueprint $table) {
    $table->id();
    $table->foreignId('cliente_id')->constrained('clientes')->restrictOnDelete();
    $table->foreignId('plan_id')->constrained('planes')->restrictOnDelete();
    $table->string('tipo_servicio');
    $table->date('fecha_inicio');
    $table->date('fecha_fin');
    $table->decimal('costo', 10, 2);
    $table->string('estado');
    $table->string('usuario')->nullable();
    $table->string('password')->nullable();
    $table->json('detalles')->nullable();
    $table->timestamps();
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('suscripciones');
    }
};
