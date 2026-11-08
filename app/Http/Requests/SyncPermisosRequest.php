<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SyncPermisosRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'permisos' => 'present|array',
            'permisos.*' => 'integer|distinct|exists:permisos,id',
        ];
    }

    public function messages(): array
    {
        return [
            'permisos.present' => 'Debe enviarse la lista de permisos',
            'permisos.array' => 'Los permisos deben enviarse como lista',
            'permisos.*.exists' => 'Uno de los permisos seleccionados no existe',
            'permisos.*.distinct' => 'Hay permisos repetidos en la selección',
        ];
    }
}
