<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreClienteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }


    public function rules(): array
    {
        return [
            'nombre' => 'required|string|max:255',
            'razon_social' => 'nullable|string|max:255',
            'numero_documento' => 'required|string|max:50|unique:clientes,numero_documento',
            'telefono' => 'nullable|string|max:20',
            'correo' => 'nullable|email|max:255',
        ];
    }

    public function messages(): array
    {
        return [
            'nombre.required' => 'El nombre es requerido',
            'numero_documento.required' => 'El CI / NIT es requerido',
            'numero_documento.unique' => 'Ya existe un cliente con ese número de documento',
            'correo.email' => 'El correo debe ser válido',
        ];
    }
}
