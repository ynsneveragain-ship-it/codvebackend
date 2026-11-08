<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rastro del aviso de vencimiento que se manda desde el panel del dashboard.
 *
 * Las dos tablas con fecha_fin (las unicas que caducan) llevan ahora el mismo
 * par de columnas:
 *
 *   estado_notificacion  'notificado' cuando ya se le aviso al cliente, y NULL
 *                        mientras no se le haya avisado nunca. El NULL es el
 *                        estado normal, no un dato que falte: por eso no hay
 *                        valor por defecto ni un 'pendiente' que habria que ir
 *                        poniendo a mano en las filas que ya existen.
 *   fecha_notificacion   cuando se mando ese aviso.
 *
 * El panel las mira para decidir si pinta el boton "Notificar" o el texto
 * "Notificado": vaciar las dos vuelve a habilitar el boton de esa fila.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dominios', function (Blueprint $table) {
            $table->string('estado_notificacion')->nullable()->after('fecha_fin');
            $table->timestamp('fecha_notificacion')->nullable()->after('estado_notificacion');
        });

        Schema::table('suscripciones', function (Blueprint $table) {
            $table->string('estado_notificacion')->nullable()->after('estado');
            $table->timestamp('fecha_notificacion')->nullable()->after('estado_notificacion');
        });
    }

    public function down(): void
    {
        Schema::table('dominios', function (Blueprint $table) {
            $table->dropColumn(['estado_notificacion', 'fecha_notificacion']);
        });

        Schema::table('suscripciones', function (Blueprint $table) {
            $table->dropColumn(['estado_notificacion', 'fecha_notificacion']);
        });
    }
};
