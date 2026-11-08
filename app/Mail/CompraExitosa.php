<?php

namespace App\Mail;

use App\Models\Pago;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Aviso al cliente de que su compra se cobro y quedo activa.
 *
 * Lo manda Pago::enviarAvisoDeCompra(), que solo lo hace cuando el cobro se
 * acaba de confirmar de verdad (ver Pago::confirmarPago). No se envia desde
 * ningun controlador a proposito: las dos vias de confirmacion (la
 * notificacion del banco y la consulta de estado desde la pantalla) acaban en
 * el modelo, y mandarlo desde cada una duplicaria el correo cuando llegan las
 * dos.
 *
 * El Mailable NO decide nada: recibe el pago con 'cliente' y
 * 'suscripcion.plan' / 'suscripcion.dominios' ya cargados y solo los pinta.
 */
class CompraExitosa extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Pago $pago,
        /** Correo al que se manda; puede ser el de la venta y no el de la ficha */
        public string $destinatario
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Compra realizada con éxito · Pago #' . $this->pago->id,
        );
    }

    public function content(): Content
    {
        $suscripcion = $this->pago->suscripcion;

        return new Content(
            view: 'emails.compra-exitosa',
            with: [
                'pago' => $this->pago,
                'cliente' => $this->pago->cliente,
                'suscripcion' => $suscripcion,
                'plan' => $suscripcion?->plan,
                'dominios' => $suscripcion?->dominios ?? collect(),
                // El nombre que se escribio en ESTA venta, si lo hay: a un
                // cliente que ya existia no se le pisa la ficha maestra, asi
                // que el de la ficha puede ser otro (ver CompraController)
                'nombre' => $suscripcion?->detalles['contacto_indicado']['nombre']
                    ?? $this->pago->cliente?->nombre
                    ?? 'cliente',
                'empresa' => (string) config('mail.from.name', config('app.name')),
                'moneda' => (string) config('bnb.moneda', 'BOB'),
            ],
        );
    }
}
