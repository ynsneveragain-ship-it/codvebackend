<?php

namespace App\Http\Controllers;

use App\Mail\VencimientoDominio;
use App\Mail\VencimientoSuscripcion;
use App\Models\Dominio;
use App\Models\Suscripcion;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Lo que está a punto de caducar: alimenta la campana del dashboard.
 *
 * No es un CRUD ni un reporte: es una consulta de solo lectura que cruza dos
 * tablas (dominios y suscripciones, las dos únicas con fecha_fin) y devuelve
 * ya resuelto el color del aviso, para que la pantalla no tenga que repetir
 * el criterio por su cuenta.
 */
class VencimientoController extends Controller
{
    public function index(): JsonResponse
    {
        $diasAviso = max(1, (int) config('avisos.dias_aviso'));
        $diasUrgente = max(1, (int) config('avisos.dias_urgente'));

        $hoy = now()->startOfDay();
        $limite = $hoy->copy()->addDays($diasAviso);

        // '<= limite' sin suelo por abajo: lo ya vencido entra también, con
        // los días en negativo (ver config/avisos.php)
        $dominios = Dominio::with('suscripcion.cliente')
            ->whereDate('fecha_fin', '<=', $limite->toDateString())
            ->orderBy('fecha_fin')
            ->get()
            ->map(fn ($d) => [
                'id' => $d->id,
                'nombre' => $d->nombre,
                'proveedor' => $d->proveedor,
                'cliente' => $d->suscripcion?->cliente?->nombre,
                'suscripcion_id' => $d->suscripcion_id,
                'fecha_fin' => $d->fecha_fin?->format('Y-m-d'),
                'dias_restantes' => $this->diasHasta($hoy, $d->fecha_fin),
                // Con que el panel sepa si ya se aviso: null = nunca
                'estado_notificacion' => $d->estado_notificacion,
                'fecha_notificacion' => $d->fecha_notificacion?->format('Y-m-d H:i'),
            ])
            ->values()
            ->all();

        $suscripciones = Suscripcion::with(['cliente', 'plan'])
            ->whereDate('fecha_fin', '<=', $limite->toDateString())
            ->orderBy('fecha_fin')
            ->get()
            ->map(fn ($s) => [
                'id' => $s->id,
                'cliente' => $s->cliente?->nombre,
                'plan' => $s->plan?->nombre,
                'tipo_servicio' => $s->tipo_servicio,
                'estado' => $s->estado,
                'costo' => $s->costo,
                'fecha_fin' => $s->fecha_fin?->format('Y-m-d'),
                'dias_restantes' => $this->diasHasta($hoy, $s->fecha_fin),
                'estado_notificacion' => $s->estado_notificacion,
                'fecha_notificacion' => $s->fecha_notificacion?->format('Y-m-d H:i'),
            ])
            ->values()
            ->all();

        $todos = array_merge($dominios, $suscripciones);
        $total = count($todos);

        // Un null en dias_restantes (fecha_fin vacía, que la BD no permite
        // hoy) no debe colarse como "urgente" por comparar null < 7
        $urgentes = array_filter(
            $todos,
            fn ($i) => $i['dias_restantes'] !== null && $i['dias_restantes'] < $diasUrgente
        );

        return response()->json([
            // 'rojo' manda sobre 'amarillo': basta uno urgente
            'nivel' => count($urgentes) > 0 ? 'rojo' : ($total > 0 ? 'amarillo' : 'verde'),
            'totales' => [
                'dominios' => count($dominios),
                'suscripciones' => count($suscripciones),
                'total' => $total,
                'urgentes' => count($urgentes),
            ],
            'parametros' => [
                'dias_aviso' => $diasAviso,
                'dias_urgente' => $diasUrgente,
            ],
            'dominios' => $dominios,
            'suscripciones' => $suscripciones,
        ]);
    }

    /**
     * Botón "Notificar" de un DOMINIO de la lista: le escribe al cliente.
     *
     * Lo dispara una persona mirando el panel, no el sistema: no hay ningún
     * envío automático de vencimientos. UN SOLO AVISO POR DOMINIO: al salir el
     * correo se marcan estado_notificacion y fecha_notificacion, el panel pasa
     * a pintar "Notificado" en vez del botón, y una segunda llamada se rechaza
     * con 409. Ese 409 no es decorativo: el panel puede llevar un rato abierto
     * y otra persona haber avisado ya.
     */
    public function notificarDominio(Dominio $dominio): JsonResponse
    {
        if ($sinPermiso = $this->sinPermisoDeNotificar()) {
            return $sinPermiso;
        }

        if (filled($dominio->estado_notificacion)) {
            return response()->json([
                'message' => 'Este dominio ya fue notificado'
                    . ($dominio->fecha_notificacion
                        ? ' el ' . $dominio->fecha_notificacion->format('d/m/Y H:i')
                        : ''),
                'estado_notificacion' => $dominio->estado_notificacion,
                'fecha_notificacion' => $dominio->fecha_notificacion?->format('Y-m-d H:i'),
            ], 409);
        }

        $dominio->load('suscripcion.cliente');

        $destinatario = $dominio->suscripcion?->correoDeAviso();

        if (! $destinatario) {
            return response()->json([
                'message' => 'Este dominio no tiene un correo al que avisar: '
                    . 'revisa la ficha del cliente de su suscripción',
            ], 422);
        }

        $dias = $this->diasHasta(now()->startOfDay(), $dominio->fecha_fin);

        try {
            Mail::to($destinatario)->send(new VencimientoDominio($dominio, $dias));
        } catch (\Throwable $e) {
            // Aquí sí se devuelve el fallo: a diferencia del aviso de compra
            // (que cuelga de un cobro ya escrito), esto es una acción suelta y
            // quien pulsó el botón tiene que saber que no salió.
            Log::error('No se pudo notificar el vencimiento de un dominio', [
                'dominio' => $dominio->id,
                'correo' => $destinatario,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'No se pudo enviar el correo. Revisa la configuración de envío e inténtalo de nuevo',
            ], 502);
        }

        // Se marca DESPUÉS del envío y solo si no lanzó: la fila dice "se
        // avisó", así que no puede quedar marcada por un correo que no salió.
        $dominio->estado_notificacion = (string) config('avisos.estado_notificado');
        $dominio->fecha_notificacion = now();
        $dominio->save();

        Log::info('Aviso de vencimiento enviado', [
            'tipo' => 'dominio',
            'dominio' => $dominio->id,
            'correo' => $destinatario,
            'dias_restantes' => $dias,
            'usuario_id' => request()->user()?->id,
        ]);

        return response()->json([
            'message' => "Aviso enviado a {$destinatario}",
            'correo' => $destinatario,
            'dias_restantes' => $dias,
            // El panel los usa para pintar "Notificado" sin recargar la lista
            'estado_notificacion' => $dominio->estado_notificacion,
            'fecha_notificacion' => $dominio->fecha_notificacion->format('Y-m-d H:i'),
        ]);
    }

    /**
     * Botón "Notificar" de una SUSCRIPCIÓN de la lista. Mismo criterio que el
     * de dominios, con su propio Mailable y su propia plantilla, y también un
     * solo aviso por suscripción.
     */
    public function notificarSuscripcion(Suscripcion $suscripcion): JsonResponse
    {
        if ($sinPermiso = $this->sinPermisoDeNotificar()) {
            return $sinPermiso;
        }

        if (filled($suscripcion->estado_notificacion)) {
            return response()->json([
                'message' => 'Esta suscripción ya fue notificada'
                    . ($suscripcion->fecha_notificacion
                        ? ' el ' . $suscripcion->fecha_notificacion->format('d/m/Y H:i')
                        : ''),
                'estado_notificacion' => $suscripcion->estado_notificacion,
                'fecha_notificacion' => $suscripcion->fecha_notificacion?->format('Y-m-d H:i'),
            ], 409);
        }

        $suscripcion->load(['cliente', 'plan', 'dominios']);

        $destinatario = $suscripcion->correoDeAviso();

        if (! $destinatario) {
            return response()->json([
                'message' => 'Esta suscripción no tiene un correo al que avisar: '
                    . 'revisa la ficha del cliente',
            ], 422);
        }

        $dias = $this->diasHasta(now()->startOfDay(), $suscripcion->fecha_fin);

        try {
            Mail::to($destinatario)->send(new VencimientoSuscripcion($suscripcion, $dias));
        } catch (\Throwable $e) {
            Log::error('No se pudo notificar el vencimiento de una suscripcion', [
                'suscripcion' => $suscripcion->id,
                'correo' => $destinatario,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'No se pudo enviar el correo. Revisa la configuración de envío e inténtalo de nuevo',
            ], 502);
        }

        $suscripcion->estado_notificacion = (string) config('avisos.estado_notificado');
        $suscripcion->fecha_notificacion = now();
        $suscripcion->save();

        Log::info('Aviso de vencimiento enviado', [
            'tipo' => 'suscripcion',
            'suscripcion' => $suscripcion->id,
            'correo' => $destinatario,
            'dias_restantes' => $dias,
            'usuario_id' => request()->user()?->id,
        ]);

        return response()->json([
            'message' => "Aviso enviado a {$destinatario}",
            'correo' => $destinatario,
            'dias_restantes' => $dias,
            'estado_notificacion' => $suscripcion->estado_notificacion,
            'fecha_notificacion' => $suscripcion->fecha_notificacion->format('Y-m-d H:i'),
        ]);
    }

    /**
     * Días que faltan, en negativo si ya pasó. Se compara a días completos
     * (ambas fechas al arrancar el día) para que "mañana" sea siempre 1 y no
     * dependa de la hora a la que se mire.
     */
    private function diasHasta(Carbon $hoy, ?Carbon $fecha): ?int
    {
        return $fecha ? (int) $hoy->diffInDays($fecha->copy()->startOfDay(), false) : null;
    }

    /**
     * Quién puede escribirle a un cliente.
     *
     * index() la ve cualquier usuario autenticado, incluido 'visita', porque
     * solo mira. Notificar NO es mirar: manda un correo a una persona real y
     * no se puede deshacer, así que los roles de config('avisos.roles_sin_notificar')
     * (hoy 'visita') reciben un 403, igual que ya pasa con registrar ventas y
     * cobrar por QR. Es el mismo apaño a mano que allí: el proyecto todavía no
     * comprueba permisos de verdad (ver 5.1 del contexto).
     */
    private function sinPermisoDeNotificar(): ?JsonResponse
    {
        $rol = request()->user()?->rol?->nombre;

        if (in_array($rol, (array) config('avisos.roles_sin_notificar', []), true)) {
            return response()->json(['message' => 'Tu rol no permite enviar avisos a los clientes'], 403);
        }

        return null;
    }
}
