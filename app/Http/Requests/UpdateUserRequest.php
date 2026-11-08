<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $userId = $this->route('user');

        return [
            'primer_nombre' => 'required|string|max:100',
            'segundo_nombre' => 'nullable|string|max:100',
            'apellido_paterno' => 'required|string|max:100',
            'apellido_materno' => 'nullable|string|max:100',
            'correo' => ['required', 'email', Rule::unique('users', 'correo')->ignore($userId)],
            'password' => 'nullable|string|min:8|confirmed',
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
            'password.min' => 'La contraseña debe tener al menos 8 caracteres',
            'password.confirmed' => 'Las contraseñas no coinciden',
            'rol_id.exists' => 'El rol seleccionado no existe',
        ];
    }
}
