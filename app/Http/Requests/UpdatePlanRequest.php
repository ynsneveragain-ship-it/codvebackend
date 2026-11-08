<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nombre' => 'required|string|max:255',
            'vigencia' => 'required|integer|min:1|max:120',
            'precio' => 'required|numeric|min:0',
            'precio_oferta' => 'nullable|numeric|min:0|lte:precio',
            'caracteristicas' => 'nullable|array',
            'caracteristicas.*' => 'string|max:255',
        ];
    }

    public function messages(): array
    {
        return [
            'nombre.required' => 'El nombre del plan es requerido',
            'vigencia.required' => 'La vigencia es requerida',
            'vigencia.integer' => 'La vigencia debe ser un número de meses',
            'vigencia.min' => 'La vigencia debe ser de al menos 1 mes',
            'precio.required' => 'El precio es requerido',
            'precio.numeric' => 'El precio debe ser un número',
            'precio_oferta.lte' => 'El precio de oferta no puede superar al precio normal',
        ];
    }
}
