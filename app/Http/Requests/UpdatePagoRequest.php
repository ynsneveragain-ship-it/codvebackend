<?php

namespace App\Http\Requests;

use App\Models\Suscripcion;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Mismas reglas que StorePagoRequest: un pago no tiene ningún campo único que
 * haya que ignorar al editar. Se mantiene el par Store/Update igualmente,
 * porque es el patrón del proyecto y porque lo que se puede cambiar de un pago
 * ya cobrado seguramente acabe siendo distinto de lo que se puede crear.
 */
class UpdatePagoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'cliente_id' => 'required|integer|exists:clientes,id',
            'suscripcion_id' => 'required|integer|exists:suscripciones,id',
            'monto' => 'required|numeric|min:0',
            'fecha_pago' => 'required|date',
            'metodo_pago' => ['required', 'string', Rule::in((array) config('venta.metodos_pago'))],
            'estado' => ['required', 'string', Rule::in((array) config('venta.estados_pago'))],

            // 'qr_id' y 'additional_data' no se aceptan a propósito: los
            // escribe el cobro por QR del BNB. Dejar tocar el qr_id a mano
            // rompería la conciliación con la notificación del banco.
        ];
    }

    /**
     * La tabla guarda el cliente DOS veces: en pagos.cliente_id y a través de
     * la suscripción. Si no coinciden, la fila queda contradiciéndose sola y
     * los reportes no cuadran.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            if ($v->errors()->isNotEmpty()) {
                return;
            }

            $suscripcion = Suscripcion::find($this->input('suscripcion_id'));

            if ($suscripcion && (int) $suscripcion->cliente_id !== (int) $this->input('cliente_id')) {
                $v->errors()->add('suscripcion_id', 'Esa suscripción pertenece a otro cliente');
            }
        });
    }

    public function messages(): array
    {
        return [
            'cliente_id.required' => 'Selecciona el cliente',
            'cliente_id.exists' => 'El cliente seleccionado no existe',
            'suscripcion_id.required' => 'Selecciona la suscripción que se paga',
            'suscripcion_id.exists' => 'La suscripción seleccionada no existe',
            'monto.required' => 'El monto es requerido',
            'monto.numeric' => 'El monto debe ser un número',
            'fecha_pago.required' => 'La fecha del pago es requerida',
            'metodo_pago.required' => 'Selecciona el método de pago',
            'metodo_pago.in' => 'El método de pago no es válido',
            'estado.required' => 'Selecciona el estado',
            'estado.in' => 'El estado no es válido',
        ];
    }
}
