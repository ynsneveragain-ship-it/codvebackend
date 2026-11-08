<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePermisoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nombre' => 'required|string|max:100|unique:permisos,nombre',
            'descripcion' => 'nullable|string|max:255',
        ];
    }

    public function messages(): array
    {
        return [
            'nombre.required' => 'El nombre del permiso es requerido',
            'nombre.unique' => 'Ya existe un permiso con ese nombre',
            'nombre.max' => 'El nombre no puede exceder 100 caracteres',
        ];
    }
}
