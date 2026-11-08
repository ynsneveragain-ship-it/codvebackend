<?php

namespace App\Http\Controllers;

use App\Http\Resources\PagoResource;
use App\Http\Resources\SuscripcionResource;
use App\Models\Pago;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Cobro por QR del BNB.
 *
 * Se llama PagoQrController y no PagoController a proposito: cuando se haga el
 * CRUD de Pagos (listar, editar, dar de baja) ese nombre queda libre y no hay
 * que renombrar nada. Aqui solo vive el cobro: generar el QR, saber si ya se
 * pago y anularlo.
 *
 * Como se entera el sistema de que alguien pago:
 *   1. notificacion()  el banco llama a nuestra URL en cuanto se paga. Es la
 *                      via buena, pero necesita que el backend sea publico:
 *                      en local NO llega.
 *   2. estado()        la pantalla pregunta cada pocos segundos. Es la que
 *                      funciona en desarrollo, y la que cubre el caso de que
 *                      la notificacion se pierda.
 * Las dos acaban en Pago::confirmarPago(), que es idempotente.
 */
class PagoQrController extends Controller
{
    /**
     * Genera (o vuelve a generar) el QR de cobro de un pago pendiente.
     *
     * Volver a generarlo es la unica forma de volver a mostrarlo: el banco
     * devuelve la imagen una sola vez (ver Pago::generarQrDeCobro).
     */
    public function generar(Pago $pago): JsonResponse
    {
        if ($sinPermiso = $this->sinPermisoDeVenta()) {
            return $sinPermiso;
        }

        if ($pago->estaPagado()) {
            return response()->json([
                'message' => 'Este pago ya está cobrado',
                'pago' => new PagoResource($pago),
            ], 409);
        }

        $r = $pago->generarQrDeCobro();

        if (! $r['ok']) {
            return response()->json(['message' => $r['message']], $r['http']);
        }

        return response()->json([
            'message' => 'QR de cobro generado',
            'pago' => new PagoResource($pago),
            'qr' => $r['qr'],
        ]);
    }

    /**
     * ¿Ya se pago este QR? Si el banco dice que si, lo cobra en el sistema.
     *
     * La pantalla llama a esto cada pocos segundos mientras espera.
     */
    public function estado(Pago $pago): JsonResponse
    {
        if ($sinPermiso = $this->sinPermisoDeVenta()) {
            return $sinPermiso;
        }

        // Ya cobrado: no se molesta al banco. Ademas, una vez usado el QR, su
        // estado en el banco ya no aporta nada que no tengamos guardado.
        if ($pago->estaPagado()) {
            return response()->json([
                'pago' => new PagoResource($pago),
                // Va tambien la suscripcion porque el cobro es lo que la
                // activa: asi la pantalla no tiene que deducir ese cambio de
                // estado por su cuenta.
                'suscripcion' => new SuscripcionResource($pago->suscripcion),
                'estado' => 'pagado',
                'confirmado_ahora' => false,
            ]);
        }

        if (blank($pago->qr_id)) {
            return response()->json([
                'message' => 'Este pago no tiene un QR generado',
                'pago' => new PagoResource($pago),
            ], 409);
        }

        $r = Pago::consultarEstadoQr($pago->qr_id);

        if (! $r['ok']) {
            return response()->json(['message' => $r['message']], $r['http']);
        }

        $confirmadoAhora = false;

        if ($r['estado'] === 'pagado') {
            $confirmadoAhora = $pago->confirmarPago([
                'voucher_id' => $r['voucher_id'],
                'origen' => 'consulta',
            ]);
        }

        return response()->json([
            'pago' => new PagoResource($pago),
            // Se relee de la base a proposito: si confirmarPago() acaba de
            // activarla, una relacion ya cargada devolveria el estado viejo
            'suscripcion' => new SuscripcionResource($pago->suscripcion()->first()),
            // null si el banco manda un statusId que todavia no conocemos
            'estado' => $r['estado'],
            'status_id' => $r['status_id'],
            'expiracion' => $r['expiracion'],
            'voucher_id' => $r['voucher_id'],
            'confirmado_ahora' => $confirmadoAhora,
        ]);
    }

    /**
     * Anula el QR de un pago que no se va a cobrar ahora.
     *
     * El pago NO se borra: queda pendiente y sin QR, y se le puede generar
     * otro mas adelante. El banco solo deja anular QRs de un solo uso que no
     * se hayan usado todavia.
     */
    public function cancelar(Pago $pago): JsonResponse
    {
        if ($sinPermiso = $this->sinPermisoDeVenta()) {
            return $sinPermiso;
        }

        if ($pago->estaPagado()) {
            return response()->json([
                'message' => 'Este pago ya está cobrado, su QR no se puede anular',
                'pago' => new PagoResource($pago),
            ], 409);
        }

        if (blank($pago->qr_id)) {
            return response()->json([
                'message' => 'Este pago no tiene un QR que anular',
                'pago' => new PagoResource($pago),
            ], 409);
        }

        $anulado = $pago->qr_id;
        $r = Pago::cancelarQr($anulado);

        if (! $r['ok']) {
            return response()->json(['message' => $r['message']], $r['http']);
        }

        // Se suelta el qr_id (la columna es unica) y se deja constancia de
        // cual era, para poder rastrearlo en el banco si hiciera falta
        $pago->qr_id = null;
        $pago->additional_data = array_merge((array) $pago->additional_data, [
            'bnb_qr_anulado' => $anulado,
            'bnb_qr_anulado_en' => now()->toDateTimeString(),
        ]);
        $pago->save();

        return response()->json([
            'message' => 'QR anulado',
            'pago' => new PagoResource($pago),
        ]);
    }

    /**
     * Webhook ReceiveNotification: el banco avisa de que un QR fue pagado.
     *
     * Es PUBLICO (el banco no tiene un token nuestro), asi que lo unico que lo
     * protege es el secreto de la URL acordada con el banco, si se configura
     * BNB_WEBHOOK_TOKEN. Sin ese secreto, cualquiera que adivine la ruta y un
     * QRId podria dar un pago por cobrado.
     *
     * El banco espera siempre { success, message } y guarda esa respuesta en
     * su propio registro (notificationResponse), asi que aqui NO se devuelven
     * errores HTTP: se contesta 200 explicando el problema.
     */
    public function notificacion(Request $request, ?string $token = null): JsonResponse
    {
        $esperado = config('bnb.webhook_token');

        if (filled($esperado) && ! hash_equals((string) $esperado, (string) $token)) {
            Log::warning('BNB: notificacion con token invalido', ['ip' => $request->ip()]);

            abort(404);
        }

        $qrId = $request->input('QRId', $request->input('qrId'));

        $pago = filled($qrId) ? Pago::where('qr_id', (string) $qrId)->first() : null;

        // Via de reserva: additionalData lleva 'pago:<id>' desde que se genero
        // el QR. Cubre el caso de que el QR se haya regenerado y el banco avise
        // de uno anterior.
        if (! $pago) {
            $adicional = (string) $request->input('additionalData', '');

            if (preg_match('/pago:(\d+)/', $adicional, $coincidencias)) {
                $pago = Pago::find((int) $coincidencias[1]);
            }
        }

        if (! $pago) {
            Log::warning('BNB: notificacion de un QR desconocido', ['cuerpo' => $request->all()]);

            return response()->json([
                'success' => false,
                'message' => 'QR no registrado en el sistema',
            ]);
        }

        $pago->confirmarPago([
            'voucher_id' => $request->input('VoucherId'),
            'titular' => $request->input('originName'),
            'banco_origen' => $request->input('sourceBankId'),
            'datos_adicionales' => $request->input('additionalData') ?: null,
            'fecha' => Pago::fechaDeNotificacion($request->input('TransactionDateTime')),
            'origen' => 'notificacion',
        ]);

        return response()->json(['success' => true, 'message' => 'OK']);
    }

    // ------------------------------------------------------------------

    /**
     * Mismo criterio que StoreCompraRequest: el cobro es parte de la venta, y
     * los roles que no pueden vender tampoco pueden cobrar. Se comprueba aqui
     * a mano porque estos tres metodos no tienen FormRequest propio (no
     * reciben cuerpo que validar).
     */
    private function sinPermisoDeVenta(): ?JsonResponse
    {
        $rol = request()->user()?->rol?->nombre;

        if (in_array($rol, (array) config('venta.roles_sin_venta', []), true)) {
            return response()->json(['message' => 'Tu rol no permite registrar ventas'], 403);
        }

        return null;
    }
}
