<?php

namespace App\Http\Controllers;

use App\Models\Permiso;
use App\Http\Requests\StorePermisoRequest;
use App\Http\Requests\UpdatePermisoRequest;
use App\Http\Requests\SyncRolesRequest;
use App\Http\Resources\PermisoResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Barryvdh\DomPDF\Facade\Pdf;

class PermisoController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => PermisoResource::collection(
                Permiso::withCount('roles')->orderBy('nombre')->get()
            ),
        ]);
    }

    public function show(Permiso $permiso): JsonResponse
    {
        return response()->json([
            'permiso' => new PermisoResource($permiso->load('roles')->loadCount('roles')),
        ]);
    }

    public function store(StorePermisoRequest $request): JsonResponse
    {
        $permiso = Permiso::create($request->only('nombre', 'descripcion'));

        return response()->json([
            'message' => 'Permiso creado exitosamente',
            'permiso' => new PermisoResource($permiso),
        ], 201);
    }

    public function update(UpdatePermisoRequest $request, Permiso $permiso): JsonResponse
    {
        $permiso->update($request->only('nombre', 'descripcion'));

        return response()->json([
            'message' => 'Permiso actualizado exitosamente',
            'permiso' => new PermisoResource($permiso),
        ]);
    }

    public function destroy(Permiso $permiso): JsonResponse
    {
        $permiso->delete();

        return response()->json([
            'message' => 'Permiso eliminado exitosamente',
        ]);
    }

    public function syncRoles(SyncRolesRequest $request, Permiso $permiso): JsonResponse
    {
        $permiso->roles()->sync($request->input('roles', []));

        return response()->json([
            'message' => 'Roles del permiso actualizados',
            'permiso' => new PermisoResource($permiso->load('roles')->loadCount('roles')),
        ]);
    }

    public function reporte(): JsonResponse
    {
        return response()->json(['reporte' => $this->armarReporte()]);
    }

    public function reportePdf(): Response
    {
        $pdf = Pdf::loadView('reportes.permisos', ['r' => $this->armarReporte()])
            ->setPaper('a4', 'portrait');

        return $pdf->download('reporte-permisos-' . now()->format('Y-m-d') . '.pdf');
    }

    private function armarReporte(): array
    {
        $permisos = Permiso::with('roles')->withCount('roles')->orderBy('nombre')->get();

        return [
            'generado_en' => now()->format('d/m/Y H:i'),
            'totales' => [
                'permisos' => $permisos->count(),
                'asignados' => $permisos->where('roles_count', '>', 0)->count(),
                'sin_asignar' => $permisos->where('roles_count', 0)->count(),
            ],
            'permisos' => $permisos->map(fn ($p) => [
                'nombre' => $p->nombre,
                'descripcion' => $p->descripcion,
                'total_roles' => $p->roles_count,
                'asignado' => $p->roles_count > 0,
                'roles' => $p->roles->pluck('nombre')->values()->all(),
            ])->values()->all(),
        ];
    }
}
