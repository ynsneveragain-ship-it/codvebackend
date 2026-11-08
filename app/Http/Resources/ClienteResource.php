<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClienteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'razon_social' => $this->razon_social,
            'numero_documento' => $this->numero_documento,
            'telefono' => $this->telefono,
            'correo' => $this->correo,
            'suscripciones_count' => $this->whenCounted('suscripciones'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
