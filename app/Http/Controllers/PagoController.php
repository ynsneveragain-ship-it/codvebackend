<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePagoRequest;
use App\Http\Requests\UpdatePagoRequest;
use App\Http\Resources\PagoResource;
use App\Models\Pago;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * CRUD de Pagos.
 *
 * El cobro por QR del BNB vive aparte, en PagoQrController: aquí solo se
 * administran las filas de la tabla (altas a mano, correcciones, bajas).
 */
class PagoController extends Controller
{
    public function index(): JsonResponse
    {
        $pagos = Pago::with(['cliente', 'suscripcion'])
            ->orderByDesc('fecha_pago')
            ->orderByDesc('id')
            ->paginate(15);

        return response()->json([
            'data' => PagoResource::collection($pagos->items()),
            'pagination' => [
                'total' => $pagos->total(),
                'per_page' => $pagos->perPage(),
                'current_page' => $pagos->currentPage(),
                'last_page' => $pagos->lastPage(),
            ],
        ]);
    }

    public function show(Pago $pago): JsonResponse
    {
        return response()->json([
            'pago' => new PagoResource($pago->load(['cliente', 'suscripcion'])),
        ]);
    }

    public function store(StorePagoRequest $request): JsonResponse
    {
        $pago = Pago::create($request->validated());

        return response()->json([
            'message' => 'Pago registrado exitosamente',
            'pago' => new PagoResource($pago->load(['cliente', 'suscripcion'])),
        ], 201);
    }

    public function update(UpdatePagoRequest $request, Pago $pago): JsonResponse
    {
        // OJO: esto NO toca la suscripción. Marcar un pago como 'pagado' desde
        // aquí no la activa; eso solo lo hace Pago::confirmarPago cuando lo
        // confirma el banco. Si se corrige un pago a mano, repasar también el
        // estado de su suscripción.
        $pago->update($request->validated());

        return response()->json([
            'message' => 'Pago actualizado exitosamente',
            'pago' => new PagoResource($pago->load(['cliente', 'suscripcion'])),
        ]);
    }

    public function destroy(Pago $pago): JsonResponse
    {
        // Aquí no hace falta el corte previo de 409 que llevan Cliente, Plan y
        // Suscripción: ninguna tabla apunta a pagos.
        $pago->delete();

        return response()->json([
            'message' => 'Pago eliminado exitosamente',
        ]);
    }

    public function reporte(): JsonResponse
    {
        return response()->json(['reporte' => $this->armarReporte()]);
    }

    public function reportePdf(): Response
    {
        $pdf = Pdf::loadView('reportes.pagos', ['r' => $this->armarReporte()])
            ->setPaper('a4', 'landscape');

        return $pdf->download('reporte-pagos-' . now()->format('Y-m-d') . '.pdf');
    }

    private function armarReporte(): array
    {
        // Sin paginar: el reporte cubre todos los pagos, no la página actual
        $pagos = Pago::with(['cliente', 'suscripcion'])
            ->orderByDesc('fecha_pago')
            ->get();

        $estados = (array) config('venta.estados');
        $cobrados = $pagos->where('estado', $estados['pago_pagado']);
        $pendientes = $pagos->where('estado', $estados['pago_pendiente']);

        return [
            'generado_en' => now()->format('d/m/Y H:i'),
            'totales' => [
                'pagos' => $pagos->count(),
                'cobrados' => $cobrados->count(),
                'pendientes' => $pendientes->count(),
                'monto_cobrado' => number_format((float) $cobrados->sum('monto'), 2),
                'monto_pendiente' => number_format((float) $pendientes->sum('monto'), 2),
                'con_qr' => $pagos->whereNotNull('qr_id')->count(),
            ],
            // Cuánto se ha cobrado por cada medio. Mismo formato que el
            // 'por_rol' del reporte de usuarios.
            'por_metodo' => $pagos->groupBy('metodo_pago')
                ->map(fn ($grupo, $metodo) => [
                    'metodo' => $metodo ?: 'Sin método',
                    'pagos' => $grupo->count(),
                    'monto' => number_format((float) $grupo->sum('monto'), 2),
                    'cobrado' => number_format(
                        (float) $grupo->where('estado', $estados['pago_pagado'])->sum('monto'),
                        2
                    ),
                ])->values()->all(),
            'pagos' => $pagos->map(fn ($p) => [
                'id' => $p->id,
                'cliente' => $p->cliente?->nombre,
                'suscripcion' => $p->suscripcion_id,
                'tipo_servicio' => $p->suscripcion?->tipo_servicio,
                'monto' => $p->monto,
                'fecha_pago' => $p->fecha_pago?->format('d/m/Y H:i'),
                'metodo_pago' => $p->metodo_pago,
                'estado' => $p->estado,
                'cobrado' => $p->estado === $estados['pago_pagado'],
                'qr_id' => $p->qr_id,
                // El comprobante que devuelve el banco, si el cobro fue por QR
                'voucher' => $p->additional_data['bnb_voucher_id'] ?? null,
            ])->values()->all(),
        ];
    }
}
