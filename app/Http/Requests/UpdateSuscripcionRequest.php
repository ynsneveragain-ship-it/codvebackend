<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSuscripcionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'cliente_id' => 'required|integer|exists:clientes,id',
            'plan_id' => 'required|integer|exists:planes,id',
            'tipo_servicio' => ['required', 'string', Rule::in([
                config('venta.tipo_sin_dominio'),
                config('venta.tipo_con_dominio'),
            ])],
            'fecha_inicio' => 'required|date',
            'fecha_fin' => 'required|date|after:fecha_inicio',
            'costo' => 'required|numeric|min:0',
            'estado' => ['required', 'string', Rule::in((array) config('venta.estados_suscripcion'))],
            'usuario' => 'nullable|string|max:255',

            // Al editar, la contraseña en blanco significa "déjala como está",
            // no "bórrala": el formulario no puede traerla de vuelta porque el
            // Resource nunca la devuelve. Quien la descarta es el controlador.
            'password' => 'nullable|string|max:255',
        ];
    }

    public function messages(): array
    {
        return [
            'cliente_id.required' => 'Selecciona el cliente',
            'cliente_id.exists' => 'El cliente seleccionado no existe',
            'plan_id.required' => 'Selecciona el plan',
            'plan_id.exists' => 'El plan seleccionado no existe',
            'tipo_servicio.required' => 'Selecciona el tipo de servicio',
            'tipo_servicio.in' => 'El tipo de servicio no es válido',
            'fecha_inicio.required' => 'La fecha de inicio es requerida',
            'fecha_fin.required' => 'La fecha de fin es requerida',
            'fecha_fin.after' => 'La fecha de fin debe ser posterior a la de inicio',
            'costo.required' => 'El costo es requerido',
            'costo.numeric' => 'El costo debe ser un número',
            'estado.required' => 'Selecciona el estado',
            'estado.in' => 'El estado no es válido',
        ];
    }
}
