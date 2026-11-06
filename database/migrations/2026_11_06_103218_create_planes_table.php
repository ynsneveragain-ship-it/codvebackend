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
       Schema::create('planes', function (Blueprint $table) {
    $table->id();
    $table->string('nombre');
    $table->integer('vigencia');
    $table->decimal('precio', 10, 2);
    $table->decimal('precio_oferta', 10, 2)->nullable();
    $table->json('caracteristicas')->nullable();
    $table->timestamps();
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('planes');
    }
};
