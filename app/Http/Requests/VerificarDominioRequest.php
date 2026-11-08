<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class VerificarDominioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    // Se normaliza antes de validar: Porkbun no acepta espacios, mayusculas,
    // ni el esquema o la barra final que la gente suele pegar del navegador
    protected function prepareForValidation(): void
    {
        $nombre = (string) $this->input('nombre', '');
        $nombre = trim(mb_strtolower($nombre));
        $nombre = preg_replace('#^https?://#', '', $nombre);
        $nombre = preg_replace('#^www\.#', '', $nombre);
        $nombre = rtrim($nombre, '/');

        $this->merge(['nombre' => $nombre]);
    }

    public function rules(): array
    {
        return [
            // El patron exige al menos un punto y una extension de 2+ letras.
            // Filtrar aqui evita gastar una consulta del limite de Porkbun en
            // algo que la API rechazaria con INVALID_DOMAIN.
            'nombre' => [
                'required',
                'string',
                'max:253',
                'regex:/^(?=.{1,253}$)([a-z0-9]([a-z0-9-]*[a-z0-9])?\.)+[a-z]{2,}$/',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'nombre.required' => 'Escribe un nombre de dominio para verificar',
            'nombre.regex' => 'El nombre no parece un dominio válido (ejemplo: midominio.com)',
        ];
    }
}
