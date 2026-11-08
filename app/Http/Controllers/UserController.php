<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\Rol;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Barryvdh\DomPDF\Facade\Pdf;

class UserController extends Controller
{
    public function index(): JsonResponse
    {
        $users = User::with('rol')->paginate(15);

        return response()->json([
            'data' => UserResource::collection($users->items()),
            'pagination' => [
                'total' => $users->total(),
                'per_page' => $users->perPage(),
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
            ],
        ]);
    }

    public function show(User $user): JsonResponse
    {
        return response()->json([
            'user' => new UserResource($user->load('rol')),
        ]);
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $user = User::create([
            'primer_nombre' => $request->primer_nombre,
            'segundo_nombre' => $request->segundo_nombre,
            'apellido_paterno' => $request->apellido_paterno,
            'apellido_materno' => $request->apellido_materno,
            'correo' => $request->correo,
            'password' => Hash::make($request->password),
            'telefono' => $request->telefono,
            'rol_id' => $request->rol_id,
            'activo' => $request->boolean('activo', true),
        ]);

        return response()->json([
            'message' => 'Usuario creado exitosamente',
            'user' => new UserResource($user->load('rol')),
        ], 201);
    }

    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        $data = [
            'primer_nombre' => $request->primer_nombre,
            'segundo_nombre' => $request->segundo_nombre,
            'apellido_paterno' => $request->apellido_paterno,
            'apellido_materno' => $request->apellido_materno,
            'correo' => $request->correo,
            'telefono' => $request->telefono,
            'rol_id' => $request->rol_id,
            'activo' => $request->boolean('activo'),
        ];

        if ($request->password) {
            $data['password'] = Hash::make($request->password);
        }

        $user->update($data);

        return response()->json([
            'message' => 'Usuario actualizado exitosamente',
            'user' => new UserResource($user->load('rol')),
        ]);
    }

    public function destroy(User $user): JsonResponse
    {
        $user->delete();

        return response()->json([
            'message' => 'Usuario eliminado exitosamente',
        ]);
    }

    public function reporte(): JsonResponse
    {
        return response()->json(['reporte' => $this->armarReporte()]);
    }

    public function reportePdf(): Response
    {
        $pdf = Pdf::loadView('reportes.usuarios', ['r' => $this->armarReporte()])
            ->setPaper('a4', 'portrait');

        return $pdf->download('reporte-usuarios-' . now()->format('Y-m-d') . '.pdf');
    }

    private function armarReporte(): array
    {
        $usuarios = User::with('rol')->orderBy('primer_nombre')->get();

        // Se cuentan todos los roles, incluidos los que no tiene nadie
        $porRol = Rol::orderBy('nombre')->get()->map(fn ($rol) => [
            'nombre' => $rol->nombre,
            'total' => $usuarios->where('rol_id', $rol->id)->count(),
        ])->values()->all();

        $sinRol = $usuarios->whereNull('rol_id')->count();
        if ($sinRol > 0) {
            $porRol[] = ['nombre' => 'Sin rol', 'total' => $sinRol];
        }

        return [
            'generado_en' => now()->format('d/m/Y H:i'),
            'totales' => [
                'usuarios' => $usuarios->count(),
                'activos' => $usuarios->where('activo', true)->count(),
                'inactivos' => $usuarios->where('activo', false)->count(),
                'con_acceso' => $usuarios->whereNotNull('ultimo_login')->count(),
                'nunca_ingresaron' => $usuarios->whereNull('ultimo_login')->count(),
            ],
            'por_rol' => $porRol,
            'usuarios' => $usuarios->map(fn ($u) => [
                'nombre' => trim($u->primer_nombre . ' ' . $u->apellido_paterno),
                'correo' => $u->correo,
                'telefono' => $u->telefono,
                'rol' => $u->rol?->nombre ?? 'Sin rol',
                'activo' => (bool) $u->activo,
                'ultimo_login' => $u->ultimo_login?->format('d/m/Y H:i'),
            ])->values()->all(),
        ];
    }
}
