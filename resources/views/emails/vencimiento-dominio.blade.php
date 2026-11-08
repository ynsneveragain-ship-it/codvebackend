{{--
    Aviso de vencimiento de un DOMINIO, mandado a mano desde el panel de
    vencimientos del dashboard.

    Estilos EN LINEA y maquetado con tablas, por lo mismo que el correo de
    compra: los clientes de correo descartan las hojas de estilo. Los colores
    de marca van escritos a mano (#351f63 primario, #1fca9d acento) y el
    ambar/rojo del aviso sale del mismo criterio que la campana: urgente por
    debajo de los dias de config('avisos.dias_urgente').

    Es una plantilla propia, separada de la de suscripciones aunque se
    parezcan: ver la nota en app/Mail/VencimientoDominio.php.
--}}
@php
    $urgente = $vencido || ($dias !== null && $dias < (int) config('avisos.dias_urgente'));
    $fondoAviso = $urgente ? '#fef2f2' : '#fffbeb';
    $bordeAviso = $urgente ? '#dc2626' : '#f59e0b';
    $textoAviso = $urgente ? '#991b1b' : '#92400e';
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Tu dominio está por vencer</title>
</head>
<body style="margin:0; padding:0; background-color:#f4f4f7; font-family:Roboto, Arial, Helvetica, sans-serif; color:#1f2937;">

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f4f4f7; padding:24px 12px;">
    <tr>
        <td align="center">

            <table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="width:100%; max-width:600px; background-color:#ffffff; border-radius:10px; overflow:hidden; border:1px solid #e5e7eb;">

                {{-- Cabecera --}}
                <tr>
                    <td style="background-color:#351f63; padding:28px 32px; text-align:center;">
                        <p style="margin:0; font-size:13px; letter-spacing:1px; text-transform:uppercase; color:#c4b5fd;">
                            {{ $empresa }}
                        </p>
                        <h1 style="margin:8px 0 0; font-family:Poppins, Arial, Helvetica, sans-serif; font-size:23px; color:#ffffff;">
                            @if ($vencido)
                                Tu dominio ha vencido
                            @else
                                Tu dominio está por vencer
                            @endif
                        </h1>
                    </td>
                </tr>

                {{-- El aviso, con su plazo --}}
                <tr>
                    <td style="padding:28px 32px 8px;">
                        <p style="margin:0 0 14px; font-size:16px;">Hola {{ $nombre }},</p>
                        <p style="margin:0 0 18px; font-size:15px; line-height:1.6; color:#374151;">
                            Te escribimos para avisarte de que el registro de tu dominio
                            <strong>{{ $dominio->nombre }}</strong>
                            @if ($vencido)
                                venció y el dominio puede quedar libre para otra persona.
                            @else
                                está llegando a su fecha de caducidad.
                            @endif
                        </p>

                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:{{ $fondoAviso }}; border-left:4px solid {{ $bordeAviso }}; border-radius:6px; margin:0 0 24px;">
                            <tr>
                                <td style="padding:14px 16px;">
                                    <p style="margin:0; font-size:13px; color:{{ $textoAviso }}; text-transform:uppercase; letter-spacing:.5px;">
                                        {{ $vencido ? 'Venció el' : 'Vence el' }}
                                        {{ $dominio->fecha_fin?->format('d/m/Y') }}
                                    </p>
                                    <p style="margin:4px 0 0; font-size:24px; font-weight:bold; color:{{ $textoAviso }};">
                                        @if ($dias === null)
                                            Sin fecha de vencimiento
                                        @elseif ($dias < 0)
                                            Venció hace {{ abs($dias) }} día(s)
                                        @elseif ($dias === 0)
                                            Vence hoy
                                        @elseif ($dias === 1)
                                            Vence mañana
                                        @else
                                            Faltan {{ $dias }} días
                                        @endif
                                    </p>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>

                {{-- Detalle --}}
                <tr>
                    <td style="padding:0 32px;">
                        <h2 style="margin:0 0 10px; font-family:Poppins, Arial, Helvetica, sans-serif; font-size:16px; color:#351f63;">
                            Detalle del dominio
                        </h2>

                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="font-size:14px; border-collapse:collapse;">
                            <tr>
                                <td style="padding:9px 0; color:#6b7280; border-bottom:1px solid #f3f4f6;">Dominio</td>
                                <td style="padding:9px 0; text-align:right; font-weight:bold; border-bottom:1px solid #f3f4f6;">{{ $dominio->nombre }}</td>
                            </tr>
                            @if (filled($dominio->proveedor))
                                <tr>
                                    <td style="padding:9px 0; color:#6b7280; border-bottom:1px solid #f3f4f6;">Registrado en</td>
                                    <td style="padding:9px 0; text-align:right; font-weight:bold; border-bottom:1px solid #f3f4f6;">{{ $dominio->proveedor }}</td>
                                </tr>
                            @endif
                            <tr>
                                <td style="padding:9px 0; color:#6b7280; border-bottom:1px solid #f3f4f6;">Periodo</td>
                                <td style="padding:9px 0; text-align:right; font-weight:bold; border-bottom:1px solid #f3f4f6;">
                                    {{ $dominio->fecha_inicio?->format('d/m/Y') }} &mdash; {{ $dominio->fecha_fin?->format('d/m/Y') }}
                                </td>
                            </tr>
                            @if ($suscripcion)
                                <tr>
                                    <td style="padding:9px 0; color:#6b7280; border-bottom:1px solid #f3f4f6;">Suscripción</td>
                                    <td style="padding:9px 0; text-align:right; font-weight:bold; border-bottom:1px solid #f3f4f6;">
                                        #{{ $suscripcion->id }} · {{ $suscripcion->tipo_servicio }}
                                    </td>
                                </tr>
                            @endif
                        </table>
                    </td>
                </tr>

                {{-- Pie --}}
                <tr>
                    <td style="padding:24px 32px 32px;">
                        <p style="margin:0 0 6px; font-size:14px; line-height:1.6; color:#374151;">
                            @if ($vencido)
                                Si quieres recuperarlo, responde a este correo cuanto antes:
                                pasado el plazo de gracia del registrador, el dominio vuelve a
                                estar disponible para cualquiera.
                            @else
                                Para renovarlo, responde a este correo y te indicamos los pasos.
                                Si ya no lo necesitas, no tienes que hacer nada.
                            @endif
                        </p>
                        <p style="margin:18px 0 0; font-size:12px; color:#9ca3af;">
                            Este aviso te lo envía {{ $empresa }}.
                        </p>
                    </td>
                </tr>

            </table>

        </td>
    </tr>
</table>

</body>
</html>
