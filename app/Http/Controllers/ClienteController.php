<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Http\Requests\StoreClienteRequest;
use App\Http\Requests\UpdateClienteRequest;
use App\Http\Resources\ClienteResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Barryvdh\DomPDF\Facade\Pdf;

class ClienteController extends Controller
{
    /**
     * Listado paginado, salvo que se pida ?todos=1.
     *
     * Ese modo existe por los desplegables: el formulario de Suscripciones
     * necesita poder elegir CUALQUIER cliente, y con la primera página de 15
     * se quedaría fuera el resto. La respuesta mantiene la misma forma, con
     * 'pagination' describiendo una única página, para que quien ya la lee no
     * tenga que distinguir los dos casos.
     */
    public function index(): JsonResponse
    {
        if (request()->boolean('todos')) {
            $todos = Cliente::withCount('suscripciones')->orderBy('nombre')->get();

            return response()->json([
                'data' => ClienteResource::collection($todos),
                'pagination' => [
                    'total' => $todos->count(),
                    'per_page' => $todos->count(),
                    'current_page' => 1,
                    'last_page' => 1,
                ],
            ]);
        }

        $clientes = Cliente::withCount('suscripciones')->orderBy('nombre')->paginate(15);

        return response()->json([
            'data' => ClienteResource::collection($clientes->items()),
            'pagination' => [
                'total' => $clientes->total(),
                'per_page' => $clientes->perPage(),
                'current_page' => $clientes->currentPage(),
                'last_page' => $clientes->lastPage(),
            ],
        ]);
    }

    public function show(Cliente $cliente): JsonResponse
    {
        return response()->json([
            'cliente' => new ClienteResource($cliente->loadCount('suscripciones')),
        ]);
    }

    public function store(StoreClienteRequest $request): JsonResponse
    {
        $cliente = Cliente::create($request->validated());

        return response()->json([
            'message' => 'Cliente creado exitosamente',
            'cliente' => new ClienteResource($cliente),
        ], 201);
    }

    public function update(UpdateClienteRequest $request, Cliente $cliente): JsonResponse
    {
        $cliente->update($request->validated());

        return response()->json([
            'message' => 'Cliente actualizado exitosamente',
            'cliente' => new ClienteResource($cliente),
        ]);
    }

    public function destroy(Cliente $cliente): JsonResponse
    {
        // Las FK son ON DELETE RESTRICT: sin este corte, la BD lanzaria un 500
        if ($cliente->suscripciones()->exists() || $cliente->pagos()->exists()) {
            return response()->json([
                'message' => 'No se puede eliminar: el cliente tiene suscripciones o pagos asociados',
            ], 409);
        }

        $cliente->delete();

        return response()->json([
            'message' => 'Cliente eliminado exitosamente',
        ]);
    }

    public function reporte(): JsonResponse
    {
        return response()->json(['reporte' => $this->armarReporte()]);
    }

    public function reportePdf(): Response
    {
        $pdf = Pdf::loadView('reportes.clientes', ['r' => $this->armarReporte()])
            ->setPaper('a4', 'landscape');

        return $pdf->download('reporte-clientes-' . now()->format('Y-m-d') . '.pdf');
    }

    private function armarReporte(): array
    {
        // Sin paginar: el reporte cubre toda la cartera, no la pagina actual
        $clientes = Cliente::withCount(['suscripciones', 'pagos'])->orderBy('nombre')->get();

        return [
            'generado_en' => now()->format('d/m/Y H:i'),
            'totales' => [
                'clientes' => $clientes->count(),
                'con_suscripcion' => $clientes->where('suscripciones_count', '>', 0)->count(),
                'sin_suscripcion' => $clientes->where('suscripciones_count', 0)->count(),
                'con_correo' => $clientes->whereNotNull('correo')->count(),
                'con_telefono' => $clientes->whereNotNull('telefono')->count(),
            ],
            'clientes' => $clientes->map(fn ($c) => [
                'nombre' => $c->nombre,
                'razon_social' => $c->razon_social,
                'numero_documento' => $c->numero_documento,
                'correo' => $c->correo,
                'telefono' => $c->telefono,
                'suscripciones' => $c->suscripciones_count,
                'pagos' => $c->pagos_count,
                'activo' => $c->suscripciones_count > 0,
            ])->values()->all(),
        ];
    }
}
