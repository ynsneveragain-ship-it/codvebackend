<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDominioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    // Hasta el 2026-11-08 aqui habia un prepareForValidation que, si no
    // llegaba suscripcion_id, lo ponia a 1 (la suscripcion de prueba), porque
    // el formulario no la preguntaba. Ya existe el CRUD de Suscripciones y el
    // formulario la elige, asi que el campo es requerido de verdad: completar
    // una clave foranea a dedo es justo lo que hace que acaben colgando filas
    // de la suscripcion equivocada.

    public function rules(): array
    {
        return [
            'suscripcion_id' => 'required|integer|exists:suscripciones,id',
            'nombre' => 'required|string|max:255|unique:dominios,nombre',
            'proveedor' => 'required|string|max:255',
            'precio_real' => 'required|numeric|min:0',
            'precio' => 'required|numeric|min:0',
            'fecha_inicio' => 'required|date',
            'fecha_fin' => 'required|date|after:fecha_inicio',
        ];
    }

    public function messages(): array
    {
        return [
            'suscripcion_id.required' => 'Selecciona la suscripción a la que pertenece el dominio',
            'suscripcion_id.exists' => 'La suscripción indicada no existe',
            'nombre.required' => 'El nombre del dominio es requerido',
            'nombre.unique' => 'Ya existe un dominio registrado con ese nombre',
            'proveedor.required' => 'El proveedor es requerido',
            'precio_real.required' => 'El precio real (costo para la empresa) es requerido',
            'precio_real.numeric' => 'El precio real debe ser un número',
            'precio.required' => 'El precio es requerido',
            'precio.numeric' => 'El precio debe ser un número',
            'fecha_inicio.required' => 'La fecha de inicio es requerida',
            'fecha_fin.required' => 'La fecha de fin es requerida',
            'fecha_fin.after' => 'La fecha de fin debe ser posterior a la de inicio',
        ];
    }
}
