<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class Dominio extends Model
{
    protected $table = 'dominios';

    protected $fillable = [
        'suscripcion_id',
        'nombre',
        'proveedor',
        'precio_real',
        'precio',
        'fecha_inicio',
        'fecha_fin',
        // Rastro del aviso de vencimiento que se manda desde el panel del
        // dashboard. NULL = nunca se le aviso al cliente (ver 5.26).
        'estado_notificacion',
        'fecha_notificacion',
    ];

    protected function casts(): array
    {
        return [
            'precio_real' => 'decimal:2',
            'precio' => 'decimal:2',
            'fecha_inicio' => 'date',
            'fecha_fin' => 'date',
            'fecha_notificacion' => 'datetime',
        ];
    }

    public function suscripcion()
    {
        return $this->belongsTo(Suscripcion::class, 'suscripcion_id');
    }

    // ------------------------------------------------------------------
    // Consulta de disponibilidad en Porkbun
    //
    // Vive en el modelo, y no en un controlador, porque la usan DOS sitios:
    // el boton Verificar (DominioController) y el registro de una venta
    // (CompraController). Si estuviera duplicada, una venta podria aceptar un
    // dominio que el verificador rechaza. El proyecto no tiene capa de
    // servicios, asi que el modelo es el punto compartido que corresponde.
    // ------------------------------------------------------------------

    /**
     * Pregunta a Porkbun si un dominio esta libre y a que precio.
     *
     * Devuelve siempre un array con la misma forma:
     *   ok          bool    la consulta se pudo resolver
     *   disponible  ?bool   true/false, o null si no se pudo interpretar
     *   motivo      ?string 'ocupado' | 'tld_no_soportado' | null
     *   precio      ?array  solo cuando esta disponible
     *   http        int     codigo a devolver cuando ok es false
     *   message     ?string mensaje para el usuario cuando ok es false
     *   codigo      ?string codigo de error de Porkbun, si lo hubo
     *
     * No se cachea a proposito: el sentido de volver a preguntar justo antes
     * de registrar una venta es saber si el dominio sigue libre EN ESE
     * momento, y una respuesta guardada de hace un minuto no lo garantiza.
     */
    public static function consultarDisponibilidad(string $nombre): array
    {
        $clave = config('services.porkbun.key');
        $secreto = config('services.porkbun.secret');

        if (blank($clave) || blank($secreto)) {
            return self::fallo(503, 'La verificación no está configurada: faltan PORKBUN_API_KEY y '
                . 'PORKBUN_SECRET_API_KEY en el .env del backend');
        }

        // Extension que sabemos que Porkbun no vende: no se consulta siquiera
        if (self::tldNoSoportado($nombre)) {
            return self::noSoportado();
        }

        $url = rtrim(config('services.porkbun.url'), '/') . '/domain/checkDomain/' . rawurlencode($nombre);

        try {
            $respuesta = Http::timeout((int) config('services.porkbun.timeout'))
                ->acceptJson()
                ->post($url, ['apikey' => $clave, 'secretapikey' => $secreto]);
        } catch (ConnectionException $e) {
            Log::warning('Porkbun: no hubo conexion', ['dominio' => $nombre, 'error' => $e->getMessage()]);

            return self::fallo(504, 'No se pudo contactar con Porkbun. Revisa la conexión e inténtalo de nuevo');
        }

        $cuerpo = $respuesta->json() ?? [];

        // Porkbun limita a 10 verificaciones cada 10 segundos por cuenta
        if ($respuesta->status() === 429) {
            $segundos = (int) ($respuesta->header('Retry-After') ?: ($cuerpo['ttlRemaining'] ?? 0));

            return self::fallo(429, $segundos > 0
                ? "Demasiadas verificaciones seguidas. Espera {$segundos} s y vuelve a intentarlo"
                : 'Demasiadas verificaciones seguidas. Espera unos segundos y vuelve a intentarlo');
        }

        if ($respuesta->failed() || ($cuerpo['status'] ?? null) === 'ERROR') {
            // La lista de config/porkbun.php no puede cubrir las ~600 extensiones
            // que Porkbun no vende. Si se cuela una, el propio Porkbun lo dice
            // con INVALID_TLD y se le da el mismo trato que si estuviera en la
            // lista, en vez de soltar su mensaje en ingles.
            if (($cuerpo['code'] ?? null) === 'INVALID_TLD') {
                return self::noSoportado();
            }

            Log::warning('Porkbun: respuesta de error', [
                'dominio' => $nombre,
                'http' => $respuesta->status(),
                'cuerpo' => $cuerpo,
            ]);

            return self::fallo(
                502,
                $cuerpo['message'] ?? 'Porkbun no pudo verificar este dominio',
                $cuerpo['code'] ?? null
            );
        }

        $datos = $cuerpo['response'] ?? [];
        $disponible = self::interpretarDisponibilidad($datos, $nombre);

        return [
            'ok' => true,
            'disponible' => $disponible,
            'motivo' => $disponible === false ? 'ocupado' : null,
            // Porkbun devuelve precio tambien para los ocupados (lo que costaria
            // si se liberase). Se omite a proposito: mostrarlo haria pensar que
            // el dominio se puede comprar.
            'precio' => $disponible === true ? self::extraerPrecio($datos) : null,
            'http' => 200,
            'message' => null,
            'codigo' => null,
        ];
    }

    /**
     * ¿Termina el dominio en una extension que Porkbun no vende?
     *
     * Se compara por el final, asi que 'bo' en la lista cubre midominio.bo y
     * tambien midominio.com.bo. La lista vive en config/porkbun.php.
     */
    public static function tldNoSoportado(string $nombre): bool
    {
        foreach ((array) config('porkbun.tlds_no_soportados', []) as $tld) {
            if (str_ends_with($nombre, '.' . strtolower(trim($tld)))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Traduce el campo 'avail' de Porkbun a un booleano.
     *
     * Porkbun lo devuelve como "yes"/"no" (comprobado contra su API real),
     * pero se aceptan tambien booleanos y se devuelve null ante cualquier otra
     * cosa: es preferible decir "no se pudo determinar" a afirmar que un
     * dominio esta libre cuando no se sabe.
     */
    private static function interpretarDisponibilidad(array $datos, string $nombre): ?bool
    {
        $avail = $datos['avail'] ?? null;

        if (is_bool($avail)) {
            return $avail;
        }

        if (is_string($avail)) {
            $valor = strtolower(trim($avail));

            if (in_array($valor, ['yes', 'true', '1'], true)) {
                return true;
            }

            if (in_array($valor, ['no', 'false', '0'], true)) {
                return false;
            }
        }

        Log::warning('Porkbun: campo avail inesperado', ['dominio' => $nombre, 'response' => $datos]);

        return null;
    }

    /**
     * Resume el bloque de precios de Porkbun y le añade los importes en moneda
     * local, calculados aqui y NUNCA en el navegador (ver config/precios.php).
     *
     * Se incluye la renovacion porque en varias extensiones no coincide con el
     * primer año: .io sale a 28.12 el primer año y renueva a 51.80.
     */
    public static function extraerPrecio(array $datos): ?array
    {
        $primerAnio = $datos['price'] ?? $datos['regularPrice'] ?? null;

        if ($primerAnio === null) {
            return null;
        }

        $local = self::convertirAMonedaLocal($primerAnio);

        return [
            'primer_anio' => $primerAnio,
            'precio_regular' => $datos['regularPrice'] ?? $primerAnio,
            'renovacion' => $datos['additional']['renewal']['price'] ?? null,
            'en_oferta' => ($datos['firstYearPromo'] ?? 'no') === 'yes',
            'premium' => ($datos['premium'] ?? 'no') === 'yes',
            'moneda' => 'USD',
            // Importes ya convertidos: lo que se guarda en la tabla dominios
            'precio_real' => $local['precio_real'] ?? null,
            'precio' => $local['precio'] ?? null,
        ];
    }

    /**
     * precio_real = USD * tipo de cambio ; precio = precio_real + margen
     */
    public static function convertirAMonedaLocal(?string $precioUsd): array
    {
        if (! is_numeric($precioUsd)) {
            return ['precio_real' => null, 'precio' => null];
        }

        $costo = round((float) $precioUsd * (float) config('precios.tipo_de_cambio'), 2);

        return [
            'precio_real' => number_format($costo, 2, '.', ''),
            'precio' => number_format(round($costo + (float) config('precios.margen_sobre_costo'), 2), 2, '.', ''),
        ];
    }

    private static function fallo(int $http, string $message, ?string $codigo = null): array
    {
        return [
            'ok' => false,
            'disponible' => null,
            'motivo' => null,
            'precio' => null,
            'http' => $http,
            'message' => $message,
            'codigo' => $codigo,
        ];
    }

    private static function noSoportado(): array
    {
        return [
            'ok' => true,
            'disponible' => false,
            'motivo' => 'tld_no_soportado',
            'precio' => null,
            'http' => 200,
            'message' => null,
            'codigo' => null,
        ];
    }
}
