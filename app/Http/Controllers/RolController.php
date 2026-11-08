<?php

namespace App\Http\Controllers;

use App\Models\Rol;
use App\Http\Requests\StoreRolRequest;
use App\Http\Requests\UpdateRolRequest;
use App\Http\Requests\SyncPermisosRequest;
use App\Http\Resources\RolResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Barryvdh\DomPDF\Facade\Pdf;

class RolController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => RolResource::collection(
                Rol::withCount('permisos')->orderBy('nombre')->get()
            ),
        ]);
    }

    public function show(Rol $rol): JsonResponse
    {
        return response()->json([
            'rol' => new RolResource($rol->load('permisos')->loadCount('permisos')),
        ]);
    }

    public function store(StoreRolRequest $request): JsonResponse
    {
        $rol = Rol::create($request->only('nombre', 'descripcion'));

        return response()->json([
            'message' => 'Rol creado exitosamente',
            'rol' => new RolResource($rol),
        ], 201);
    }

    public function update(UpdateRolRequest $request, Rol $rol): JsonResponse
    {
        $rol->update($request->only('nombre', 'descripcion'));

        return response()->json([
            'message' => 'Rol actualizado exitosamente',
            'rol' => new RolResource($rol),
        ]);
    }

    public function destroy(Rol $rol): JsonResponse
    {
        $rol->delete();

        return response()->json([
            'message' => 'Rol eliminado exitosamente',
        ]);
    }

    public function syncPermisos(SyncPermisosRequest $request, Rol $rol): JsonResponse
    {
        $rol->permisos()->sync($request->input('permisos', []));

        return response()->json([
            'message' => 'Permisos del rol actualizados',
            'rol' => new RolResource($rol->load('permisos')->loadCount('permisos')),
        ]);
    }

    public function reporte(): JsonResponse
    {
        return response()->json(['reporte' => $this->armarReporte()]);
    }

    public function reportePdf(): Response
    {
        $pdf = Pdf::loadView('reportes.roles', ['r' => $this->armarReporte()])
            ->setPaper('a4', 'landscape');

        return $pdf->download('reporte-roles-' . now()->format('Y-m-d') . '.pdf');
    }

    private function armarReporte(): array
    {
        $roles = Rol::with('permisos')->withCount(['permisos', 'users'])->orderBy('nombre')->get();

        return [
            'generado_en' => now()->format('d/m/Y H:i'),
            'totales' => [
                'roles' => $roles->count(),
                'con_permisos' => $roles->where('permisos_count', '>', 0)->count(),
                'sin_permisos' => $roles->where('permisos_count', 0)->count(),
                'con_usuarios' => $roles->where('users_count', '>', 0)->count(),
                'sin_usuarios' => $roles->where('users_count', 0)->count(),
            ],
            'roles' => $roles->map(fn ($rol) => [
                'nombre' => $rol->nombre,
                'descripcion' => $rol->descripcion,
                'usuarios' => $rol->users_count,
                'total_permisos' => $rol->permisos_count,
                'permisos' => $rol->permisos->pluck('nombre')->values()->all(),
            ])->values()->all(),
        ];
    }
}
