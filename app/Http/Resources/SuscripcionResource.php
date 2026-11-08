<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SuscripcionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'cliente_id' => $this->cliente_id,
            'plan_id' => $this->plan_id,
            'tipo_servicio' => $this->tipo_servicio,
            // En Y-m-d para que entren directas en un <input type="date">
            'fecha_inicio' => $this->fecha_inicio?->format('Y-m-d'),
            'fecha_fin' => $this->fecha_fin?->format('Y-m-d'),
            'costo' => $this->costo,
            'estado' => $this->estado,
            'usuario' => $this->usuario,
            // NO se expone 'password': es la credencial del hosting del
            // cliente. El $hidden del modelo protege a Model::toArray(), pero
            // NO a este Resource, asi que la omision tiene que ser explicita.
            'detalles' => $this->detalles,
            'cliente' => new ClienteResource($this->whenLoaded('cliente')),
            'plan' => new PlanResource($this->whenLoaded('plan')),
            // Solo salen si quien consulta los pidió con withCount, así que
            // esto no cambia la respuesta de la compra, que no los carga
            'dominios_count' => $this->whenCounted('dominios'),
            'pagos_count' => $this->whenCounted('pagos'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
