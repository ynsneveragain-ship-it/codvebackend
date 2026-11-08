<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SyncRolesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'roles' => 'present|array',
            'roles.*' => 'integer|distinct|exists:roles,id',
        ];
    }

    public function messages(): array
    {
        return [
            'roles.present' => 'Debe enviarse la lista de roles',
            'roles.array' => 'Los roles deben enviarse como lista',
            'roles.*.exists' => 'Uno de los roles seleccionados no existe',
            'roles.*.distinct' => 'Hay roles repetidos en la selección',
        ];
    }
}
