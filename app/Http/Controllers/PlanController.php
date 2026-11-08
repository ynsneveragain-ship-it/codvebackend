<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Http\Requests\StorePlanRequest;
use App\Http\Requests\UpdatePlanRequest;
use App\Http\Resources\PlanResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Barryvdh\DomPDF\Facade\Pdf;

class PlanController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => PlanResource::collection(
                Plan::withCount('suscripciones')->orderBy('nombre')->get()
            ),
        ]);
    }

    public function show(Plan $plan): JsonResponse
    {
        return response()->json([
            'plan' => new PlanResource($plan->loadCount('suscripciones')),
        ]);
    }

    public function store(StorePlanRequest $request): JsonResponse
    {
        $plan = Plan::create($request->validated());

        return response()->json([
            'message' => 'Plan creado exitosamente',
            'plan' => new PlanResource($plan),
        ], 201);
    }

    public function update(UpdatePlanRequest $request, Plan $plan): JsonResponse
    {
        $plan->update($request->validated());

        return response()->json([
            'message' => 'Plan actualizado exitosamente',
            'plan' => new PlanResource($plan),
        ]);
    }

    public function destroy(Plan $plan): JsonResponse
    {
        // La FK suscripciones.plan_id es ON DELETE RESTRICT
        if ($plan->suscripciones()->exists()) {
            return response()->json([
                'message' => 'No se puede eliminar: el plan tiene suscripciones asociadas',
            ], 409);
        }

        $plan->delete();

        return response()->json([
            'message' => 'Plan eliminado exitosamente',
        ]);
    }

    public function reporte(): JsonResponse
    {
        return response()->json(['reporte' => $this->armarReporte()]);
    }

    public function reportePdf(): Response
    {
        $pdf = Pdf::loadView('reportes.planes', ['r' => $this->armarReporte()])
            ->setPaper('a4', 'landscape');

        return $pdf->download('reporte-planes-' . now()->format('Y-m-d') . '.pdf');
    }

    private function armarReporte(): array
    {
        $planes = Plan::withCount('suscripciones')->orderBy('nombre')->get();

        return [
            'generado_en' => now()->format('d/m/Y H:i'),
            'totales' => [
                'planes' => $planes->count(),
                'con_oferta' => $planes->whereNotNull('precio_oferta')->count(),
                'contratados' => $planes->where('suscripciones_count', '>', 0)->count(),
                'sin_contratar' => $planes->where('suscripciones_count', 0)->count(),
                'precio_promedio' => number_format((float) $planes->avg('precio'), 2),
            ],
            'planes' => $planes->map(fn ($p) => [
                'nombre' => $p->nombre,
                'vigencia' => $p->vigencia,
                'precio' => $p->precio,
                'precio_oferta' => $p->precio_oferta,
                'suscripciones' => $p->suscripciones_count,
                'contratado' => $p->suscripciones_count > 0,
                'caracteristicas' => $p->caracteristicas ?? [],
            ])->values()->all(),
        ];
    }
}
