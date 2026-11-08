<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'primer_nombre' => $this->primer_nombre,
            'segundo_nombre' => $this->segundo_nombre,
            'apellido_paterno' => $this->apellido_paterno,
            'apellido_materno' => $this->apellido_materno,
            'correo' => $this->correo,
            'telefono' => $this->telefono,
            'activo' => $this->activo,
            'ultimo_login' => $this->ultimo_login,
            'rol_id' => $this->rol_id,
            'rol' => [
                'id' => $this->rol?->id,
                'nombre' => $this->rol?->nombre,
            ],
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
