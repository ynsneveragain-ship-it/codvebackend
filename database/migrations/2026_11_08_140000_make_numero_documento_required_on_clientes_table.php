<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * El CI/NIT vuelve a ser obligatorio.
     *
     * Deshace la migracion 2026_11_08_120000, que lo habia hecho opcional para
     * el asistente de compra. Se decidio que toda venta debe llevar el
     * documento del cliente.
     *
     * Fallara si existen clientes con el documento en NULL: hay que
     * completarlos antes de migrar.
     */
    public function up(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->string('numero_documento')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->string('numero_documento')->nullable()->change();
        });
    }
};
