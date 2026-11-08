<?php

namespace App\Mail;

use App\Models\Suscripcion;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Aviso al cliente de que su SUSCRIPCION esta a punto de caducar.
 *
 * Lo manda el boton "Notificar" del panel de vencimientos
 * (VencimientoController::notificarSuscripcion). Igual que el de dominios:
 * lo dispara una persona, no el sistema.
 *
 * Es un Mailable aparte del de dominios a proposito (ver VencimientoDominio).
 *
 * $diasRestantes lo calcula el servidor con el mismo criterio que el panel:
 * negativo si ya vencio, null si no hay fecha.
 */
class VencimientoSuscripcion extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Suscripcion $suscripcion,
        public ?int $diasRestantes
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->diasRestantes !== null && $this->diasRestantes < 0
                ? 'Tu servicio ha vencido · Suscripción #' . $this->suscripcion->id
                : 'Tu servicio está por vencer · Suscripción #' . $this->suscripcion->id,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.vencimiento-suscripcion',
            with: [
                'suscripcion' => $this->suscripcion,
                'plan' => $this->suscripcion->plan,
                'dominios' => $this->suscripcion->dominios ?? collect(),
                'dias' => $this->diasRestantes,
                'vencido' => $this->diasRestantes !== null && $this->diasRestantes < 0,
                'nombre' => $this->suscripcion->detalles['contacto_indicado']['nombre']
                    ?? $this->suscripcion->cliente?->nombre
                    ?? 'cliente',
                'empresa' => (string) config('mail.from.name', config('app.name')),
            ],
        );
    }
}
