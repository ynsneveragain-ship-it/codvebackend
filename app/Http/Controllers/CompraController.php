<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCompraRequest;
use App\Http\Resources\ClienteResource;
use App\Http\Resources\DominioResource;
use App\Http\Resources\PagoResource;
use App\Http\Resources\SuscripcionResource;
use App\Models\Cliente;
use App\Models\Dominio;
use App\Models\Pago;
use App\Models\Plan;
use App\Models\Suscripcion;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * Registro de una venta completa desde el asistente de compra.
 *
 * Es un controlador propio y no un SuscripcionController porque el cuerpo de
 * la peticion NO es una fila de suscripciones: trae datos del cliente, del
 * dominio y del pago. Cuando llegue el CRUD de Suscripciones tendra su propio
 * controlador y su propio Request, sin chocar con este.
 */
class CompraController extends Controller
{
    public function store(StoreCompraRequest $request): JsonResponse
    {
        $datos = $request->validated();
        $plan = Plan::findOrFail($datos['plan_id']);

        if ((int) $plan->vigencia < 1) {
            return response()->json([
                'message' => 'El plan seleccionado no tiene una vigencia válida',
            ], 409);
        }

        $conDominio = filled($datos['dominio'] ?? null);
        $precioDominio = null;

        // --- Todo lo que habla con Porkbun va ANTES de abrir la transaccion:
        // nunca hay que mantener una transaccion abierta esperando a una API
        // de terceros.
        if ($conDominio) {
            if (Dominio::where('nombre', $datos['dominio'])->exists()) {
                return response()->json([
                    'message' => 'Ese dominio ya está registrado en el sistema',
                ], 409);
            }

            // Se vuelve a preguntar a proposito: entre el paso del dominio y
            // Finalizar pasan minutos y el dominio puede haberse registrado.
            $r = Dominio::consultarDisponibilidad($datos['dominio']);

            if (! $r['ok']) {
                return response()->json(
                    array_filter(
                        ['message' => $r['message'], 'codigo' => $r['codigo']],
                        fn ($v) => $v !== null
                    ),
                    $r['http']
                );
            }

            if ($r['disponible'] !== true) {
                return response()->json([
                    'message' => $r['motivo'] === 'tld_no_soportado'
                        ? "No se puede registrar {$datos['dominio']}: no se vende esa extensión"
                        : "El dominio {$datos['dominio']} ya no está disponible. Vuelve al paso del dominio y elige otro",
                ], 409);
            }

            $precioDominio = $r['precio'];

            if (blank($precioDominio['precio'] ?? null)) {
                return response()->json([
                    'message' => 'Porkbun no devolvió un precio para este dominio',
                ], 409);
            }
        }

        try {
            $suscripcion = DB::transaction(
                fn () => $this->registrar($datos, $plan, $precioDominio, $request->user()?->id)
            );
        } catch (QueryException $e) {
            // Choque contra un indice unico (1062). Son colisiones esperables
            // en uso normal, asi que se traducen a un 409 legible en vez de
            // dejar salir un 500 con SQL crudo.
            if ((int) ($e->errorInfo[1] ?? 0) === 1062) {
                return response()->json(['message' => $this->mensajeDuplicado($e)], 409);
            }

            // Cualquier otro fallo sigue siendo un 500 registrado en el log
            throw $e;
        }

        // OJO: no se recargan 'cliente' ni 'pagos'. registrar() ya dejo puestas
        // las instancias reales: la del cliente es la unica que conserva
        // wasRecentlyCreated, y la del pago es la que se actualiza aqui mismo
        // con el qr_id.
        $suscripcion->load(['plan', 'dominios']);
        $cliente = $suscripcion->cliente;
        $pago = $suscripcion->pagos->first();

        // El QR se pide al banco FUERA de la transaccion, por el mismo motivo
        // que la consulta a Porkbun: nunca hay que mantener una transaccion
        // abierta esperando a una API de terceros.
        //
        // Si el banco falla, la venta YA esta registrada y pendiente de cobro:
        // se responde 201 con qr en null y la pantalla ofrece volver a pedirlo
        // con POST /api/pagos/{pago}/qr, sin registrar la venta otra vez.
        $qr = $pago->generarQrDeCobro();

        return response()->json([
            'message' => $qr['ok']
                ? 'Venta registrada exitosamente'
                : 'Venta registrada, pero el QR de cobro no se pudo generar',
            'venta' => [
                'suscripcion' => new SuscripcionResource($suscripcion),
                'cliente' => new ClienteResource($cliente),
                // Permite que la interfaz diga "se añadió a la ficha existente de X"
                'cliente_nuevo' => (bool) $cliente->wasRecentlyCreated,
                'dominio' => $suscripcion->dominios->first()
                    ? new DominioResource($suscripcion->dominios->first())
                    : null,
                'pago' => new PagoResource($pago),
            ],
            'qr' => $qr['ok'] ? $qr['qr'] : null,
            'qr_error' => $qr['ok'] ? null : $qr['message'],
        ], 201);
    }

    /**
     * Las cuatro inserciones, en el unico orden que permiten las claves
     * foraneas: cliente -> suscripcion -> (dominio, pago).
     *
     * Se ejecuta dentro de DB::transaction(), asi que cualquier excepcion
     * revierte las cuatro. Es la primera transaccion del proyecto.
     */
    private function registrar(array $datos, Plan $plan, ?array $precioDominio, ?int $usuarioId): Suscripcion
    {
        $cliente = $this->resolverCliente($datos);

        $inicio = now()->startOfDay();
        // addMonthsNoOverflow: con addMonths, 31/01 + 1 mes daria 03/03
        $fin = $inicio->copy()->addMonthsNoOverflow((int) $plan->vigencia);

        // precio_oferta es decimal:2, asi que un 0.00 legitimo es "falsy":
        // hay que preguntar por null y no usar ?: ni un truthy check
        $costoPlan = is_null($plan->precio_oferta) ? $plan->precio : $plan->precio_oferta;
        $estados = (array) config('venta.estados');

        $suscripcion = Suscripcion::create([
            'cliente_id' => $cliente->id,
            'plan_id' => $plan->id,
            // El tipo NO lo elige el usuario: se deduce de si la venta lleva
            // dominio o no, y lo decide el servidor para que no pueda
            // contradecir a lo que realmente se creo
            'tipo_servicio' => $precioDominio !== null
                ? config('venta.tipo_con_dominio')
                : config('venta.tipo_sin_dominio'),
            'fecha_inicio' => $inicio->toDateString(),
            'fecha_fin' => $fin->toDateString(),
            'costo' => $costoPlan,
            // Toda venta nace PENDIENTE: lo que la activa es el cobro del QR
            // (Pago::confirmarPago), no el registro. El asistente ya no
            // pregunta si el cliente pago: lo dice el banco.
            'estado' => $estados['suscripcion_pendiente'],
            // El asistente no pide credenciales del hosting: se cargan despues
            'usuario' => null,
            'password' => null,
            // Rastro de la venta. Se guardan el tipo de cambio y el margen TAL
            // COMO SE APLICARON, para que un cambio posterior de config no deje
            // esta fila sin explicacion.
            'detalles' => [
                'origen' => 'asistente_compra',
                'usuario_id' => $usuarioId,
                'contacto_indicado' => [
                    'nombre' => $datos['nombre'],
                    'telefono' => $datos['telefono'],
                    'correo' => $datos['correo'],
                ],
                'dominio_precio_usd' => $precioDominio['primer_anio'] ?? null,
                'tipo_de_cambio' => (float) config('precios.tipo_de_cambio'),
                'margen' => (float) config('precios.margen_sobre_costo'),
            ],
        ]);

        $precioVentaDominio = 0;

        if ($precioDominio !== null) {
            $dominioInicio = now()->startOfDay();

            Dominio::create([
                'suscripcion_id' => $suscripcion->id,
                'nombre' => $datos['dominio'],
                'proveedor' => config('venta.proveedor_dominio'),
                'precio_real' => $precioDominio['precio_real'],
                'precio' => $precioDominio['precio'],
                'fecha_inicio' => $dominioInicio->toDateString(),
                // Los dominios se registran por años, al margen de lo que dure
                // el plan contratado
                'fecha_fin' => $dominioInicio->copy()
                    ->addYearsNoOverflow((int) config('venta.vigencia_dominio_anios', 1))
                    ->toDateString(),
            ]);

            $precioVentaDominio = (float) $precioDominio['precio'];
        }

        // El pago recoge el TOTAL cobrado (plan + dominio), aunque
        // suscripciones.costo guarde solo el plan
        $total = round((float) $costoPlan + $precioVentaDominio, 2);

        $pago = Pago::create([
            'cliente_id' => $cliente->id,
            'suscripcion_id' => $suscripcion->id,
            'monto' => $total,
            // La columna es NOT NULL, asi que de momento guarda el "registrado
            // el". Cuando el banco confirme el cobro, confirmarPago() la
            // sustituye por la fecha real de la transaccion.
            'fecha_pago' => now(),
            // El unico medio de cobro del asistente es el QR del BNB
            'metodo_pago' => (string) config('venta.metodo_bnb_qr'),
            'estado' => $estados['pago_pendiente'],
            'additional_data' => [
                'plan' => (string) $costoPlan,
                'dominio' => $precioDominio['precio'] ?? null,
                'dominio_nombre' => $datos['dominio'] ?? null,
            ],
        ]);

        // Se adjuntan las instancias que se usaron de verdad: la del cliente
        // para que store() sepa si se acaba de crear o se reutilizo, y la del
        // pago porque es la que recibe el qr_id al salir de la transaccion
        $suscripcion->setRelation('cliente', $cliente);
        $suscripcion->setRelation('pagos', collect([$pago]));

        return $suscripcion;
    }

    /**
     * Reutiliza el cliente si el CI ya existe; si no, crea uno nuevo.
     *
     * Al cliente existente NO se le tocan los datos: el nombre y el contacto
     * que se escribieron en esta venta quedan guardados en detalles de la
     * suscripcion, sin pisar la ficha maestra.
     *
     * lockForUpdate: en InnoDB, un SELECT ... FOR UPDATE que no encuentra nada
     * bloquea el hueco en el indice unico, de modo que dos ventas simultaneas
     * con el mismo CI nuevo se serializan en vez de insertar las dos.
     */
    private function resolverCliente(array $datos): Cliente
    {
        $ci = $datos['numero_documento'] ?? null;

        if (filled($ci)) {
            $existente = Cliente::where('numero_documento', $ci)->lockForUpdate()->first();

            if ($existente) {
                return $existente;
            }
        }

        return Cliente::create([
            'nombre' => $datos['nombre'],
            'razon_social' => null,
            'numero_documento' => $ci,
            'telefono' => $datos['telefono'],
            'correo' => $datos['correo'],
        ]);
    }

    private function mensajeDuplicado(QueryException $e): string
    {
        $texto = $e->getMessage();

        return match (true) {
            str_contains($texto, 'dominios_nombre_unique') => 'Ese dominio ya está registrado en el sistema',
            str_contains($texto, 'clientes_numero_documento_unique') => 'Ya existe un cliente con ese número de documento',
            str_contains($texto, 'pagos_qr_id_unique') => 'Ese QR ya está registrado',
            default => 'No se pudo registrar la venta por un dato duplicado',
        };
    }
}
