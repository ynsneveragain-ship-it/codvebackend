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
     Schema::create('clientes', function (Blueprint $table) {
    $table->id();
    $table->string('nombre');
    $table->string('razon_social')->nullable();
    $table->string('numero_documento')->unique();
    $table->string('telefono')->nullable();
    $table->string('correo')->nullable();
    $table->timestamps();
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clientes');
    }
};
