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
      Schema::create('dominios', function (Blueprint $table) {
    $table->id();
    $table->foreignId('suscripcion_id')->constrained('suscripciones')->cascadeOnDelete();
    $table->string('nombre')->unique();
    $table->string('proveedor');
    $table->decimal('precio_real', 10, 2);
    $table->decimal('precio', 10, 2);
    $table->date('fecha_inicio');
    $table->date('fecha_fin');
    $table->timestamps();
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dominios');
    }
};
