<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'primer_nombre' => 'required|string|max:100',
            'segundo_nombre' => 'nullable|string|max:100',
            'apellido_paterno' => 'required|string|max:100',
            'apellido_materno' => 'nullable|string|max:100',
            'correo' => 'required|email|unique:users,correo',
            'password' => 'required|string|min:8|confirmed',
            'telefono' => 'nullable|string|max:20',
            'rol_id' => 'nullable|exists:roles,id',
            'activo' => 'nullable|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'primer_nombre.required' => 'El primer nombre es requerido',
            'apellido_paterno.required' => 'El apellido paterno es requerido',
            'correo.required' => 'El correo es requerido',
            'correo.email' => 'El correo debe ser válido',
            'correo.unique' => 'El correo ya está registrado',
            'password.required' => 'La contraseña es requerida',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres',
            'password.confirmed' => 'Las contraseñas no coinciden',
            'rol_id.exists' => 'El rol seleccionado no existe',
        ];
    }
}
