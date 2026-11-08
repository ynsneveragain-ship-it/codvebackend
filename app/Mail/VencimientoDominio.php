<?php

namespace App\Mail;

use App\Models\Dominio;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Aviso al cliente de que su DOMINIO esta a punto de caducar.
 *
 * Lo manda el boton "Notificar" del panel de vencimientos
 * (VencimientoController::notificarDominio). No se envia solo: lo decide una
 * persona mirando la lista, a diferencia del correo de compra (5.25), que lo
 * dispara el cobro.
 *
 * Tiene su propio Mailable y su propia plantilla, separados de los de
 * suscripciones aunque se parezcan: es la misma decision explicita que ya
 * rige en los reportes y los modales (seccion 2 del contexto), para poder
 * cambiar el texto de uno sin tocar el otro.
 *
 * $diasRestantes viene CALCULADO POR EL SERVIDOR, con el mismo criterio que
 * el panel: negativo si ya vencio, y null si no hay fecha.
 */
class VencimientoDominio extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Dominio $dominio,
        public ?int $diasRestantes
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->diasRestantes !== null && $this->diasRestantes < 0
                ? 'Tu dominio ' . $this->dominio->nombre . ' ha vencido'
                : 'Tu dominio ' . $this->dominio->nombre . ' está por vencer',
        );
    }

    public function content(): Content
    {
        $suscripcion = $this->dominio->suscripcion;

        return new Content(
            view: 'emails.vencimiento-dominio',
            with: [
                'dominio' => $this->dominio,
                'suscripcion' => $suscripcion,
                'dias' => $this->diasRestantes,
                'vencido' => $this->diasRestantes !== null && $this->diasRestantes < 0,
                // El nombre que se escribio en la venta, si lo hay; si no, el
                // de la ficha (ver Suscripcion::correoDeAviso)
                'nombre' => $suscripcion?->detalles['contacto_indicado']['nombre']
                    ?? $suscripcion?->cliente?->nombre
                    ?? 'cliente',
                'empresa' => (string) config('mail.from.name', config('app.name')),
            ],
        );
    }
}
