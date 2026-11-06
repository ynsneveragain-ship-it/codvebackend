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
 Schema::create('pagos', function (Blueprint $table) {
    $table->id();
    $table->foreignId('cliente_id')->constrained('clientes')->restrictOnDelete();
    $table->foreignId('suscripcion_id')->constrained('suscripciones')->restrictOnDelete();
    $table->decimal('monto', 10, 2);
    $table->dateTime('fecha_pago');
    $table->string('metodo_pago');
    $table->string('qr_id')->nullable()->unique();
    $table->json('additional_data')->nullable();
    $table->string('estado');
    $table->timestamps();
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pagos');
    }
};
