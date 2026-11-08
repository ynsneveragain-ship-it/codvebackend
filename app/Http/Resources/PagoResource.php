<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PagoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'cliente_id' => $this->cliente_id,
            'suscripcion_id' => $this->suscripcion_id,
            'monto' => $this->monto,
            'fecha_pago' => $this->fecha_pago?->format('Y-m-d H:i'),
            'metodo_pago' => $this->metodo_pago,
            'estado' => $this->estado,
            'qr_id' => $this->qr_id,
            'additional_data' => $this->additional_data,
            // Solo salen si quien consulta cargó las relaciones, así que la
            // respuesta del cobro por QR sigue igual que antes
            'cliente' => $this->whenLoaded('cliente', fn () => [
                'id' => $this->cliente->id,
                'nombre' => $this->cliente->nombre,
                'numero_documento' => $this->cliente->numero_documento,
            ]),
            'suscripcion' => $this->whenLoaded('suscripcion', fn () => [
                'id' => $this->suscripcion->id,
                'tipo_servicio' => $this->suscripcion->tipo_servicio,
                'estado' => $this->suscripcion->estado,
            ]),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
