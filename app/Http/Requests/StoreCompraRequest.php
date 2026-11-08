<?php

namespace App\Http\Requests;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;

class StoreCompraRequest extends FormRequest
{
    /**
     * Primera comprobacion de permisos del proyecto.
     *
     * OJO: esto NO arregla el punto 5.1 del contexto. El resto de la API sigue
     * abierta a cualquier usuario autenticado; aqui se cierra solo este
     * endpoint, porque el dashboard (con el boton Comprar) lo ve tambien el
     * rol visita y esto escribe dinero en la base.
     *
     * Es una lista de roles EXCLUIDOS, no de permitidos, para no dejar fuera
     * sin querer a los usuarios sin rol ni a los roles que se creen despues.
     */
    public function authorize(): bool
    {
        $rol = $this->user()?->rol?->nombre;

        return ! in_array($rol, (array) config('venta.roles_sin_venta', []), true);
    }

    // Sin esto Laravel devuelve "This action is unauthorized." en ingles
    protected function failedAuthorization(): void
    {
        throw new AuthorizationException('Tu rol no permite registrar ventas');
    }

    /**
     * Se normaliza antes de validar:
     *  - el dominio igual que en VerificarDominioRequest, para que la venta no
     *    acepte una forma que el verificador rechazaria
     *  - el CI solo se recorta; es obligatorio, asi que un blanco lo rechaza
     *    la propia regla 'required'
     */
    protected function prepareForValidation(): void
    {
        $dominio = (string) $this->input('dominio', '');
        $dominio = trim(mb_strtolower($dominio));
        $dominio = preg_replace('#^https?://#', '', $dominio);
        $dominio = preg_replace('#^www\.#', '', $dominio);
        $dominio = rtrim($dominio, '/');

        $this->merge([
            'dominio' => $dominio === '' ? null : $dominio,
            'numero_documento' => trim((string) $this->input('numero_documento', '')),
        ]);
    }

    public function rules(): array
    {
        return [
            'plan_id' => 'required|integer|exists:planes,id',
            // Datos del cliente
            'nombre' => 'required|string|max:255',
            // Sin regla unique a proposito: un CI repetido NO es un error,
            // significa que hay que reutilizar ese cliente
            'numero_documento' => 'required|string|max:50',
            'telefono' => 'required|string|max:20',
            'correo' => 'required|email|max:255',

            // El dominio es opcional: se puede vender un hosting suelto. De
            // que venga o no depende el tipo_servicio, que decide el servidor.
            'dominio' => [
                'nullable',
                'string',
                'max:253',
                'regex:/^(?=.{1,253}$)([a-z0-9]([a-z0-9-]*[a-z0-9])?\.)+[a-z]{2,}$/',
            ],

            // El cobro NO se describe aqui: desde que el pago va por QR del
            // BNB, el metodo es siempre ese y el estado lo decide el banco.
            // Antes llegaban 'metodo_pago' y 'cobrado'; aceptarlos permitiria
            // dar una venta por cobrada sin que nadie hubiera pagado.
        ];
    }

    public function messages(): array
    {
        return [
            'plan_id.required' => 'Selecciona un plan',
            'plan_id.exists' => 'El plan seleccionado no existe',
            'nombre.required' => 'El nombre del cliente es requerido',
            'numero_documento.required' => 'El CI / NIT del cliente es requerido',
            'numero_documento.max' => 'El CI / NIT es demasiado largo',
            'telefono.required' => 'El celular es requerido',
            'correo.required' => 'El correo es requerido',
            'correo.email' => 'El correo debe ser válido',
            'dominio.regex' => 'El dominio no es válido (ejemplo: midominio.com)',
        ];
    }
}
