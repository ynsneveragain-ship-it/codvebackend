<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DominioResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'suscripcion_id' => $this->suscripcion_id,
            'nombre' => $this->nombre,
            'proveedor' => $this->proveedor,
            // precio_real es el costo para la empresa y precio lo que paga el
            // cliente. Hoy se expone a cualquier usuario autenticado porque no
            // hay control de acceso por rol en la API (ver CONTEXTO 5.1).
            'precio_real' => $this->precio_real,
            'precio' => $this->precio,
            // En formato Y-m-d para que entren directas en un <input type="date">
            'fecha_inicio' => $this->fecha_inicio?->format('Y-m-d'),
            'fecha_fin' => $this->fecha_fin?->format('Y-m-d'),
            'suscripcion' => $this->whenLoaded('suscripcion', fn () => [
                'id' => $this->suscripcion->id,
                'tipo_servicio' => $this->suscripcion->tipo_servicio,
                'estado' => $this->suscripcion->estado,
                'cliente' => $this->suscripcion->cliente?->nombre,
            ]),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
