<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'correo' => 'required|email|exists:users,correo',
            'password' => 'required|string|min:6',
        ];
    }

    public function messages(): array
    {
        return [
            'correo.required' => 'El correo es requerido',
            'correo.email' => 'El correo debe ser válido',
            'correo.exists' => 'El correo no existe en el sistema',
            'password.required' => 'La contraseña es requerida',
            'password.min' => 'La contraseña debe tener al menos 6 caracteres',
        ];
    }
}
