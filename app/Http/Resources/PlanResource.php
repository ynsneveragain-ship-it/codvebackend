<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'vigencia' => $this->vigencia,
            'precio' => $this->precio,
            'precio_oferta' => $this->precio_oferta,
            'caracteristicas' => $this->caracteristicas ?? [],
            'suscripciones_count' => $this->whenCounted('suscripciones'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
