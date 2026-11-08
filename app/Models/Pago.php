<?php

namespace App\Models;

use App\Mail\CompraExitosa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class Pago extends Model
{
    protected $table = 'pagos';

    protected $fillable = [
        'cliente_id',
        'suscripcion_id',
        'monto',
        'fecha_pago',
        'metodo_pago',
        'qr_id',
        'additional_data',
        'estado',
    ];

    protected function casts(): array
    {
        return [
            'monto' => 'decimal:2',
            'fecha_pago' => 'datetime',
            'additional_data' => 'array',
        ];
    }

    public function cliente()
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }

    public function suscripcion()
    {
        return $this->belongsTo(Suscripcion::class, 'suscripcion_id');
    }

    // ------------------------------------------------------------------
    // Estado del pago
    // ------------------------------------------------------------------

    public function estaPagado(): bool
    {
        return $this->estado === config('venta.estados.pago_pagado');
    }

    /**
     * Marca el pago como cobrado y activa su suscripcion.
     *
     * Es IDEMPOTENTE y se puede llamar desde dos sitios a la vez: la
     * notificacion del banco y la consulta de estado que hace la pantalla.
     * Por eso relee la fila con lockForUpdate dentro de la transaccion: si las
     * dos llegan juntas, la segunda ve el pago ya cobrado y no hace nada.
     *
     * Devuelve true solo si ha sido ESTA llamada la que lo ha cobrado.
     *
     * $datos admite: voucher_id, titular, banco_origen, fecha (Carbon),
     * datos_adicionales y origen ('notificacion' | 'consulta').
     */
    public function confirmarPago(array $datos = []): bool
    {
        $estados = (array) config('venta.estados');

        $cobrado = (bool) DB::transaction(function () use ($datos, $estados) {
            $fresco = self::whereKey($this->getKey())->lockForUpdate()->first();

            if (! $fresco || $fresco->estado === $estados['pago_pagado']) {
                return false;
            }

            $fresco->estado = $estados['pago_pagado'];

            // La fecha del banco manda: es cuando se pago de verdad. Si no
            // llega (la consulta de estado no la devuelve), vale el ahora.
            $fresco->fecha_pago = $datos['fecha'] ?? now();

            $fresco->additional_data = array_merge(
                (array) $fresco->additional_data,
                array_filter([
                    'bnb_voucher_id' => $datos['voucher_id'] ?? null,
                    'bnb_titular' => $datos['titular'] ?? null,
                    'bnb_banco_origen' => $datos['banco_origen'] ?? null,
                    'bnb_datos_adicionales' => $datos['datos_adicionales'] ?? null,
                    'bnb_confirmado_en' => now()->toDateTimeString(),
                    'bnb_confirmado_por' => $datos['origen'] ?? null,
                ], fn ($v) => $v !== null && $v !== '')
            );

            $fresco->save();

            // El cobro es lo que pone en marcha el servicio contratado
            $suscripcion = $fresco->suscripcion;

            if ($suscripcion && $suscripcion->estado !== $estados['suscripcion_activa']) {
                $suscripcion->estado = $estados['suscripcion_activa'];
                $suscripcion->save();
            }

            // Para que quien llamo vea los valores nuevos sin recargar a mano
            $this->setRawAttributes($fresco->getAttributes(), true);
            $this->unsetRelation('suscripcion');

            return true;
        });

        // FUERA de la transaccion y solo si ha sido esta llamada la que ha
        // cobrado: el correo se manda una vez por pago, cuando ya estan
        // escritos el cobro y la activacion de la suscripcion. Si se mandara
        // dentro, un fallo del correo revertiria un pago que el banco ya dio
        // por bueno, y una transaccion abierta esperando al servidor de
        // correo es el mismo error que esperar a Porkbun o al BNB con ella
        // abierta.
        if ($cobrado) {
            $this->enviarAvisoDeCompra();
        }

        return $cobrado;
    }

    /**
     * Avisa al cliente por correo de que su compra se realizo con exito.
     *
     * Solo lo llama confirmarPago(), que es la unica puerta por la que pasan
     * las dos vias de cobro (la notificacion del banco y la consulta de
     * estado desde la pantalla). Al colgar de ahi hereda su idempotencia: si
     * las dos llegan, solo una devuelve true y solo esa manda el correo.
     *
     * NUNCA propaga una excepcion. El cobro ya esta escrito y el banco espera
     * un 200: que no haya servidor de correo configurado, o que rechace el
     * envio, no puede tumbar la respuesta a la notificacion ni dejar el pago
     * a medias. Lo que falle queda en el log.
     */
    public function enviarAvisoDeCompra(): bool
    {
        try {
            // Se relee con las relaciones que pinta la plantilla. Hace falta
            // de verdad: por aqui se entra tanto desde el webhook (donde el
            // modelo viene de un where por qr_id, sin nada cargado) como
            // desde la pantalla.
            $pago = self::with(['cliente', 'suscripcion.plan', 'suscripcion.dominios'])
                ->find($this->getKey());

            if (! $pago) {
                return false;
            }

            $destinatario = $pago->correoDeAviso();

            if (! $destinatario) {
                // No es un error del sistema: una venta cargada a mano puede
                // no tener correo. Queda constancia para poder avisar a mano.
                Log::info('Compra cobrada sin correo al que avisar', ['pago' => $pago->id]);

                return false;
            }

            Mail::to($destinatario)->send(new CompraExitosa($pago, $destinatario));

            Log::info('Aviso de compra enviado', [
                'pago' => $pago->id,
                'correo' => $destinatario,
            ]);

            return true;
        } catch (\Throwable $e) {
            Log::error('No se pudo enviar el aviso de compra', [
                'pago' => $this->getKey(),
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * A que correo se avisa.
     *
     * La REGLA (el correo de la venta antes que el de la ficha) vive en
     * Suscripcion::correoDeAviso, porque la comparten los tres correos del
     * sistema: este y los dos avisos de vencimiento del panel del dashboard.
     * Aqui solo se añade un candidato mas al final, el cliente del propio
     * pago, para el caso raro de un pago sin suscripcion.
     */
    private function correoDeAviso(): ?string
    {
        return Suscripcion::primerCorreoValido([
            $this->suscripcion?->detalles['contacto_indicado']['correo'] ?? null,
            $this->suscripcion?->cliente?->correo,
            $this->cliente?->correo,
        ]);
    }

    /**
     * Genera el QR de cobro de ESTE pago y lo deja guardado.
     *
     * Vive aqui porque lo usan dos sitios: el registro de la venta
     * (CompraController, justo despues de crear el pago) y el boton de volver
     * a generarlo (PagoQrController). Si estuviera duplicado, una de las dos
     * vias podria guardar el qr_id o la glosa de otra forma y la notificacion
     * del banco dejaria de encontrar el pago.
     *
     * Devuelve la forma de siempre (ok, http, message) y, con ok = true, la
     * clave 'qr' con todo lo que necesita la pantalla. La IMAGEN va solo en
     * esa respuesta y no se guarda: el banco la devuelve una unica vez, al
     * crearla, y no tiene servicio para recuperarla, pero son kilobytes de
     * base64 por fila para algo que caduca en un dia. Por eso "volver a
     * mostrar el QR" es en realidad generar uno nuevo.
     */
    public function generarQrDeCobro(): array
    {
        // Si ya habia un QR vivo se anula antes de crear otro, para no dejar
        // cobros sueltos que el cliente podria pagar por error. Es "en lo
        // posible": si el banco no deja anularlo, se sigue adelante igual.
        if (filled($this->qr_id)) {
            $anulacion = self::cancelarQr($this->qr_id);

            if (! $anulacion['ok']) {
                Log::warning('BNB: no se pudo anular el QR anterior', [
                    'pago' => $this->id,
                    'qr_id' => $this->qr_id,
                    'motivo' => $anulacion['message'],
                ]);
            }
        }

        $glosa = trim((string) config('bnb.glosa_prefijo')) . ' ' . $this->id;

        $qr = self::generarQr(
            monto: (string) $this->monto,
            glosa: $glosa,
            // Via de reserva para conciliar la notificacion si el QR se
            // regenero y el banco avisa de uno anterior
            datosAdicionales: 'pago:' . $this->id
        );

        if (! $qr['ok']) {
            return $qr;
        }

        $this->qr_id = $qr['qr_id'];
        $this->additional_data = array_merge((array) $this->additional_data, [
            'bnb_glosa' => $qr['glosa'],
            'bnb_qr_expira' => $qr['expiracion'],
            'bnb_qr_generado_en' => now()->toDateTimeString(),
        ]);
        $this->save();

        return [
            'ok' => true,
            'http' => 200,
            'message' => null,
            'qr' => [
                'qr_id' => $qr['qr_id'],
                'imagen' => $qr['imagen'],
                'expiracion' => $qr['expiracion'],
                'glosa' => $qr['glosa'],
                'monto' => (string) $this->monto,
                'moneda' => (string) config('bnb.moneda'),
            ],
        ];
    }

    // ------------------------------------------------------------------
    // API de Pago QR Simple del BNB
    //
    // Vive en el modelo, y no en un controlador, por el mismo motivo que la
    // consulta a Porkbun vive en Dominio: la usan varios sitios (el registro
    // de la venta, la pantalla que espera el pago y la notificacion del
    // banco). El proyecto no tiene capa de servicios.
    //
    // Todos los metodos devuelven un array con la MISMA forma:
    //   ok       bool     la llamada se pudo resolver
    //   http     int      codigo a devolver cuando ok es false
    //   message  ?string  mensaje para el usuario cuando ok es false
    //   + los datos propios de cada llamada
    // ------------------------------------------------------------------

    private const RUTA_TOKEN = '/ClientAuthentication.API/api/v1/auth/token';
    private const RUTA_CREDENCIALES = '/ClientAuthentication.API/api/v1/auth/UpdateCredentials';
    private const RUTA_GENERAR_QR = '/QRSimple.API/api/v1/main/getQRWithImageAsync';
    private const RUTA_ESTADO_QR = '/QRSimple.API/api/v1/main/getQRStatusAsync';
    private const RUTA_CANCELAR_QR = '/QRSimple.API/api/v1/main/CancelQRByIdAsync';

    /**
     * Significado de statusId (BNB_QR_API.md). Se traduce a nombres propios
     * para no repartir numeros del banco por el frontend.
     */
    public const ESTADOS_QR = [
        1 => 'no_usado',
        2 => 'pagado',
        3 => 'expirado',
        4 => 'error',
    ];

    /**
     * Genera un QR de cobro.
     *
     * Devuelve, con ok = true:
     *   qr_id       string  el id del banco (se guarda en pagos.qr_id)
     *   imagen      string  PNG en base64, tal como lo manda el banco
     *   expiracion  string  Y-m-d
     *   glosa       string  lo que vuelve en la notificacion
     */
    public static function generarQr(
        string $monto,
        string $glosa,
        ?string $datosAdicionales = null
    ): array {
        $token = self::token();

        if (! $token['ok']) {
            return $token;
        }

        $expiracion = now()->addDays(max(1, (int) config('bnb.dias_vigencia')))->toDateString();

        $r = self::peticion($token['token'], self::RUTA_GENERAR_QR, [
            'currency' => (string) config('bnb.moneda'),
            'gloss' => $glosa,
            // El banco espera un numero, no una cadena con decimales de texto
            'amount' => (float) $monto,
            'singleUse' => (bool) config('bnb.un_solo_uso'),
            'expirationDate' => $expiracion,
            'additionalData' => (string) $datosAdicionales,
            'destinationAccountId' => (string) config('bnb.cuenta_destino'),
        ], 'generar el QR de cobro');

        if (! $r['ok']) {
            return $r;
        }

        $qrId = $r['cuerpo']['id'] ?? null;
        $imagen = $r['cuerpo']['qr'] ?? null;

        if (blank($qrId) || blank($imagen)) {
            Log::warning('BNB: respuesta de QR sin id o sin imagen', ['cuerpo' => $r['cuerpo']]);

            return self::fallo(502, 'El banco no devolvió el QR de cobro');
        }

        return [
            'ok' => true,
            'http' => 200,
            'message' => null,
            'qr_id' => (string) $qrId,
            'imagen' => (string) $imagen,
            'expiracion' => $expiracion,
            'glosa' => $glosa,
        ];
    }

    /**
     * Pregunta al banco en que estado esta un QR.
     *
     * Devuelve, con ok = true: estado (ver ESTADOS_QR), status_id,
     * expiracion y voucher_id.
     */
    public static function consultarEstadoQr(string $qrId): array
    {
        $token = self::token();

        if (! $token['ok']) {
            return $token;
        }

        $r = self::peticion(
            $token['token'],
            self::RUTA_ESTADO_QR,
            // El banco espera qrId numerico aqui, no la cadena que devolvio
            ['qrId' => (int) $qrId],
            'consultar el estado del QR'
        );

        if (! $r['ok']) {
            return $r;
        }

        $statusId = $r['cuerpo']['statusId'] ?? null;

        return [
            'ok' => true,
            'http' => 200,
            'message' => null,
            'status_id' => $statusId === null ? null : (int) $statusId,
            // null si el banco manda un statusId que todavia no conocemos
            'estado' => self::ESTADOS_QR[(int) $statusId] ?? null,
            'expiracion' => $r['cuerpo']['expirationDate'] ?? null,
            // "0" es el "no hay pago" del banco, no un comprobante
            'voucher_id' => filled($r['cuerpo']['voucherId'] ?? null) && $r['cuerpo']['voucherId'] !== '0'
                ? (string) $r['cuerpo']['voucherId']
                : null,
        ];
    }

    /**
     * Anula un QR. Solo funciona con los de un solo uso que no se hayan usado.
     */
    public static function cancelarQr(string $qrId): array
    {
        $token = self::token();

        if (! $token['ok']) {
            return $token;
        }

        $r = self::peticion(
            $token['token'],
            self::RUTA_CANCELAR_QR,
            ['qrId' => (int) $qrId],
            'cancelar el QR'
        );

        return $r['ok']
            ? ['ok' => true, 'http' => 200, 'message' => null]
            : $r;
    }

    /**
     * Cambia la contraseña (authorizationId) en el banco.
     *
     * El banco OBLIGA a hacerlo una vez antes de poder usar los demas
     * servicios. No se llama desde ninguna pantalla: lo hace a mano quien
     * administra el sistema con `php artisan bnb:credenciales`, porque cambia
     * una credencial en el banco y no se puede deshacer.
     */
    public static function actualizarCredenciales(string $nuevaAutorizacion): array
    {
        $cuenta = config('bnb.account_id');
        $actual = config('bnb.authorization_id');

        if (blank($cuenta) || blank($actual)) {
            return self::fallo(503, 'Faltan BNB_ACCOUNT_ID y BNB_AUTHORIZATION_ID en el .env del backend');
        }

        $r = self::peticionSinToken(self::RUTA_CREDENCIALES, [
            'accountId' => $cuenta,
            'actualAuthorizationId' => $actual,
            'newAuthorizationId' => $nuevaAutorizacion,
        ], 'actualizar las credenciales');

        if (! $r['ok']) {
            return $r;
        }

        // El token guardado se emitio con la credencial vieja
        Cache::forget((string) config('bnb.cache_token'));

        return ['ok' => true, 'http' => 200, 'message' => null];
    }

    /**
     * Reglas del banco para la nueva contraseña: 15 caracteres o mas, con al
     * menos una letra, un numero y uno de estos caracteres especiales.
     * Devuelve el motivo del rechazo, o null si vale.
     */
    public static function validarNuevaAutorizacion(string $valor): ?string
    {
        if (mb_strlen($valor) < 15) {
            return 'Debe tener al menos 15 caracteres';
        }

        if (! preg_match('/\pL/u', $valor)) {
            return 'Debe incluir al menos una letra';
        }

        if (! preg_match('/\d/', $valor)) {
            return 'Debe incluir al menos un número';
        }

        if (! preg_match('/[+\-\/*$<>!¡#|=]/u', $valor)) {
            return 'Debe incluir al menos un carácter especial de: + - / * $ < > ! ¡ # | =';
        }

        return null;
    }

    // ------------------------------------------------------------------
    // Fontaneria de la API del banco
    // ------------------------------------------------------------------

    /**
     * Devuelve un JWT valido, de la cache si queda vigente.
     *
     * El token caduca (el payload del JWT trae 'exp'), asi que se guarda solo
     * hasta un poco antes de esa hora. 'forzar' lo pide de nuevo: lo usa la
     * reintentada de peticion() cuando el banco responde 401.
     */
    private static function token(bool $forzar = false): array
    {
        $clave = (string) config('bnb.cache_token');

        if (! $forzar) {
            $guardado = Cache::get($clave);

            if (filled($guardado)) {
                return ['ok' => true, 'http' => 200, 'message' => null, 'token' => $guardado];
            }
        }

        $cuenta = config('bnb.account_id');
        $autorizacion = config('bnb.authorization_id');

        if (blank($cuenta) || blank($autorizacion)) {
            return self::fallo(503, 'El pago por QR no está configurado: faltan BNB_ACCOUNT_ID y '
                . 'BNB_AUTHORIZATION_ID en el .env del backend');
        }

        // BNB_QR_API.md: el PDF del banco se contradice en los nombres de este
        // cuerpo (accountId/authorizationId en la tabla de parametros,
        // username/password en el curl de ejemplo). Se prueba el documentado y,
        // si lo rechaza, la otra variante, en vez de dejar al usuario con un
        // error que no explica nada.
        $cuerpos = [
            ['accountId' => $cuenta, 'authorizationId' => $autorizacion],
            ['username' => $cuenta, 'password' => $autorizacion],
        ];

        $ultimoFallo = null;

        foreach ($cuerpos as $cuerpo) {
            $r = self::peticionSinToken(self::RUTA_TOKEN, $cuerpo, 'obtener el token del banco');

            if (! $r['ok']) {
                $ultimoFallo = $r;
                continue;
            }

            // El JWT viene en 'message', no en un campo propio
            $jwt = (string) ($r['cuerpo']['message'] ?? '');

            if (blank($jwt)) {
                $ultimoFallo = self::fallo(502, 'El banco no devolvió un token de acceso');
                continue;
            }

            Cache::put($clave, $jwt, self::vigenciaToken($jwt));

            return ['ok' => true, 'http' => 200, 'message' => null, 'token' => $jwt];
        }

        return $ultimoFallo ?? self::fallo(502, 'No se pudo obtener el token del banco');
    }

    /**
     * Segundos que se puede guardar el token: hasta su 'exp' menos un margen.
     *
     * El payload del JWT es base64url, que no es exactamente base64: hay que
     * cambiar los caracteres - y _ antes de decodificar.
     */
    private static function vigenciaToken(string $jwt): int
    {
        $margen = max(0, (int) config('bnb.margen_segundos'));
        $defecto = max(60, (int) config('bnb.ttl_por_defecto'));

        $partes = explode('.', $jwt);

        if (count($partes) < 2) {
            return $defecto;
        }

        $payload = json_decode(
            (string) base64_decode(strtr($partes[1], '-_', '+/'), false),
            true
        );

        $exp = (int) ($payload['exp'] ?? 0);

        if ($exp <= 0) {
            return $defecto;
        }

        $segundos = $exp - now()->getTimestamp() - $margen;

        // Un token que caduca ya no se guarda: mejor pedir otro
        return $segundos > 0 ? $segundos : 1;
    }

    /**
     * Peticion a un servicio autenticado del banco.
     *
     * Si responde 401 se pide un token nuevo y se reintenta UNA vez: el token
     * puede haber caducado entre que se guardo y se uso.
     */
    private static function peticion(
        string $token,
        string $ruta,
        array $cuerpo,
        string $accion,
        bool $reintento = false
    ): array {
        try {
            $respuesta = Http::timeout((int) config('bnb.timeout'))
                ->withToken($token)
                ->withHeaders(['cache-control' => 'no-cache'])
                ->acceptJson()
                ->post(self::url($ruta), $cuerpo);
        } catch (ConnectionException $e) {
            Log::warning('BNB: no hubo conexion', ['ruta' => $ruta, 'error' => $e->getMessage()]);

            return self::fallo(504, 'No se pudo contactar con el banco. Revisa la conexión e inténtalo de nuevo');
        }

        if ($respuesta->status() === 401 && ! $reintento) {
            $nuevo = self::token(forzar: true);

            if (! $nuevo['ok']) {
                return $nuevo;
            }

            return self::peticion($nuevo['token'], $ruta, $cuerpo, $accion, reintento: true);
        }

        return self::interpretar($respuesta, $ruta, $accion);
    }

    /**
     * Peticion a los servicios que NO llevan token: el de token y el de
     * cambio de credenciales.
     */
    private static function peticionSinToken(string $ruta, array $cuerpo, string $accion): array
    {
        try {
            $respuesta = Http::timeout((int) config('bnb.timeout'))
                ->withHeaders(['cache-control' => 'no-cache'])
                ->acceptJson()
                ->post(self::url($ruta), $cuerpo);
        } catch (ConnectionException $e) {
            Log::warning('BNB: no hubo conexion', ['ruta' => $ruta, 'error' => $e->getMessage()]);

            return self::fallo(504, 'No se pudo contactar con el banco. Revisa la conexión e inténtalo de nuevo');
        }

        return self::interpretar($respuesta, $ruta, $accion);
    }

    /**
     * Toda respuesta del banco trae 'success' y 'message'. Un 200 con
     * success=false ES un error, asi que no basta con mirar el codigo HTTP.
     */
    private static function interpretar($respuesta, string $ruta, string $accion): array
    {
        $cuerpo = $respuesta->json() ?? [];

        if ($respuesta->failed() || ($cuerpo['success'] ?? null) === false) {
            Log::warning('BNB: respuesta de error', [
                'ruta' => $ruta,
                'http' => $respuesta->status(),
                'cuerpo' => $cuerpo,
            ]);

            // El mensaje del banco viene en ingles y a veces vacio: se usa si
            // dice algo, y si no se explica que se estaba intentando hacer
            $mensaje = trim((string) ($cuerpo['message'] ?? ''));

            return self::fallo(
                502,
                $mensaje !== ''
                    ? "El banco rechazó la petición al {$accion}: {$mensaje}"
                    : "El banco no pudo {$accion}"
            );
        }

        return ['ok' => true, 'http' => 200, 'message' => null, 'cuerpo' => $cuerpo];
    }

    private static function url(string $ruta): string
    {
        return rtrim((string) config('bnb.url'), '/') . $ruta;
    }

    private static function fallo(int $http, string $message): array
    {
        return ['ok' => false, 'http' => $http, 'message' => $message];
    }

    /**
     * Fecha y hora que manda la notificacion del banco, en dd/MM/yyyy HH:mm:ss.
     * Devuelve null si no se puede interpretar, para no guardar una fecha
     * inventada: quien llama usa entonces el ahora.
     */
    public static function fechaDeNotificacion(?string $valor): ?Carbon
    {
        if (blank($valor)) {
            return null;
        }

        try {
            return Carbon::createFromFormat('d/m/Y H:i:s', trim($valor));
        } catch (\Throwable $e) {
            Log::warning('BNB: fecha de notificacion ilegible', ['valor' => $valor]);

            return null;
        }
    }
}
