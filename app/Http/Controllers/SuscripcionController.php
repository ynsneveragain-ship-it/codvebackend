<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSuscripcionRequest;
use App\Http\Requests\UpdateSuscripcionRequest;
use App\Http\Resources\SuscripcionResource;
use App\Models\Suscripcion;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * CRUD de Suscripciones.
 *
 * No se pisa con CompraController: aquel registra una venta entera (cliente +
 * suscripción + dominio + pago) desde el asistente, y este administra la tabla
 * suscripciones fila a fila.
 */
class SuscripcionController extends Controller
{
    /**
     * Listado paginado, salvo que se pida ?todos=1, que devuelve todas en una
     * sola página. Ese modo es para el desplegable del formulario de Pagos,
     * que tiene que poder elegir cualquier suscripción, no solo las 15
     * primeras. Igual que en ClienteController.
     */
    public function index(): JsonResponse
    {
        if (request()->boolean('todos')) {
            $todas = Suscripcion::with(['cliente', 'plan'])
                ->withCount(['dominios', 'pagos'])
                ->orderByDesc('id')
                ->get();

            return response()->json([
                'data' => SuscripcionResource::collection($todas),
                'pagination' => [
                    'total' => $todas->count(),
                    'per_page' => $todas->count(),
                    'current_page' => 1,
                    'last_page' => 1,
                ],
            ]);
        }

        $suscripciones = Suscripcion::with(['cliente', 'plan'])
            ->withCount(['dominios', 'pagos'])
            // Las más nuevas primero: es lo que se mira al entrar
            ->orderByDesc('id')
            ->paginate(15);

        return response()->json([
            'data' => SuscripcionResource::collection($suscripciones->items()),
            'pagination' => [
                'total' => $suscripciones->total(),
                'per_page' => $suscripciones->perPage(),
                'current_page' => $suscripciones->currentPage(),
                'last_page' => $suscripciones->lastPage(),
            ],
        ]);
    }

    public function show(Suscripcion $suscripcion): JsonResponse
    {
        return response()->json([
            'suscripcion' => new SuscripcionResource(
                $suscripcion->load(['cliente', 'plan'])->loadCount(['dominios', 'pagos'])
            ),
        ]);
    }

    public function store(StoreSuscripcionRequest $request): JsonResponse
    {
        $suscripcion = Suscripcion::create($request->validated());

        return response()->json([
            'message' => 'Suscripción creada exitosamente',
            'suscripcion' => new SuscripcionResource(
                $suscripcion->load(['cliente', 'plan'])->loadCount(['dominios', 'pagos'])
            ),
        ], 201);
    }

    public function update(UpdateSuscripcionRequest $request, Suscripcion $suscripcion): JsonResponse
    {
        $datos = $request->validated();

        // La contraseña del hosting solo se cambia si se escribe una nueva. El
        // formulario la manda vacía cuando no se quiere tocar, y no puede
        // devolverla rellena porque el Resource nunca la expone: sin este
        // corte, editar cualquier otro campo la borraría.
        if (blank($datos['password'] ?? null)) {
            unset($datos['password']);
        }

        $suscripcion->update($datos);

        return response()->json([
            'message' => 'Suscripción actualizada exitosamente',
            'suscripcion' => new SuscripcionResource(
                $suscripcion->load(['cliente', 'plan'])->loadCount(['dominios', 'pagos'])
            ),
        ]);
    }

    public function destroy(Suscripcion $suscripcion): JsonResponse
    {
        // pagos.suscripcion_id es ON DELETE RESTRICT: sin este corte, la BD
        // lanzaría un 500 con SQL crudo.
        if ($suscripcion->pagos()->exists()) {
            return response()->json([
                'message' => 'No se puede eliminar: la suscripción tiene pagos registrados',
            ], 409);
        }

        // Los dominios NO bloquean el borrado, al revés: dominios.suscripcion_id
        // es CASCADE, así que la BD se los llevaría por delante en silencio.
        // Por eso se corta aquí: borrar una suscripción no debería hacer
        // desaparecer dominios que alguien pagó sin avisar.
        if ($suscripcion->dominios()->exists()) {
            return response()->json([
                'message' => 'No se puede eliminar: la suscripción tiene dominios asociados. '
                    . 'Elimínalos o muévelos a otra suscripción primero',
            ], 409);
        }

        $suscripcion->delete();

        return response()->json([
            'message' => 'Suscripción eliminada exitosamente',
        ]);
    }

    public function reporte(): JsonResponse
    {
        return response()->json(['reporte' => $this->armarReporte()]);
    }

    public function reportePdf(): Response
    {
        $pdf = Pdf::loadView('reportes.suscripciones', ['r' => $this->armarReporte()])
            ->setPaper('a4', 'landscape');

        return $pdf->download('reporte-suscripciones-' . now()->format('Y-m-d') . '.pdf');
    }

    private function armarReporte(): array
    {
        // Sin paginar: el reporte cubre todas las suscripciones, no la página
        $suscripciones = Suscripcion::with(['cliente', 'plan'])
            ->withCount(['dominios', 'pagos'])
            ->orderByDesc('id')
            ->get();

        $hoy = now()->startOfDay();
        $limite = now()->startOfDay()->addDays(30);
        $estados = (array) config('venta.estados');

        return [
            'generado_en' => now()->format('d/m/Y H:i'),
            'totales' => [
                'suscripciones' => $suscripciones->count(),
                'activas' => $suscripciones->where('estado', $estados['suscripcion_activa'])->count(),
                'pendientes' => $suscripciones->where('estado', $estados['suscripcion_pendiente'])->count(),
                'por_vencer' => $suscripciones
                    ->filter(fn ($s) => $s->fecha_fin >= $hoy && $s->fecha_fin <= $limite)
                    ->count(),
                'vencidas' => $suscripciones->filter(fn ($s) => $s->fecha_fin < $hoy)->count(),
                'costo_total' => number_format((float) $suscripciones->sum('costo'), 2),
            ],
            'suscripciones' => $suscripciones->map(fn ($s) => [
                'id' => $s->id,
                'cliente' => $s->cliente?->nombre,
                'plan' => $s->plan?->nombre,
                'tipo_servicio' => $s->tipo_servicio,
                'estado' => $s->estado,
                'activa' => $s->estado === $estados['suscripcion_activa'],
                'fecha_inicio' => $s->fecha_inicio?->format('d/m/Y'),
                'fecha_fin' => $s->fecha_fin?->format('d/m/Y'),
                'vigente' => $s->fecha_fin >= $hoy,
                'dias_restantes' => $s->fecha_fin >= $hoy ? $hoy->diffInDays($s->fecha_fin) : 0,
                'costo' => $s->costo,
                'dominios' => $s->dominios_count,
                'pagos' => $s->pagos_count,
            ])->values()->all(),
        ];
    }
}
