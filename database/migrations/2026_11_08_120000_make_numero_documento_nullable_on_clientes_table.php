<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * El asistente de compra no siempre tiene el CI del cliente, asi que el
     * documento pasa a ser opcional.
     *
     * El indice unico se mantiene: MySQL permite varios NULL en una columna
     * unica, de modo que pueden convivir muchos clientes sin documento.
     * A diferencia de la migracion de users.rol_id, aqui no hay clave foranea
     * que soltar y recrear.
     */
    public function up(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->string('numero_documento')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Fallara si para entonces hay clientes con el documento en NULL
        Schema::table('clientes', function (Blueprint $table) {
            $table->string('numero_documento')->nullable(false)->change();
        });
    }
};
