{{--
    Correo que recibe el cliente cuando el banco confirma el cobro.

    Va con estilos EN LINEA y maquetado con tablas a proposito: los clientes de
    correo (Gmail, Outlook) descartan hojas de estilo y buena parte de CSS
    moderno, asi que aqui no sirven ni Tailwind ni las variables de marca de
    assets/main.css. Los colores son los mismos, escritos a mano: #351f63
    (primario) y #1fca9d (acento).
--}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Compra realizada con éxito</title>
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
                        <h1 style="margin:8px 0 0; font-family:Poppins, Arial, Helvetica, sans-serif; font-size:24px; color:#ffffff;">
                            ¡Tu compra se realizó con éxito!
                        </h1>
                    </td>
                </tr>

                {{-- Confirmacion --}}
                <tr>
                    <td style="padding:28px 32px 8px;">
                        <p style="margin:0 0 14px; font-size:16px;">Hola {{ $nombre }},</p>
                        <p style="margin:0 0 18px; font-size:15px; line-height:1.6; color:#374151;">
                            Recibimos la confirmación de tu pago
                            @if (filled($pago->fecha_pago))
                                del {{ $pago->fecha_pago->format('d/m/Y') }} a las {{ $pago->fecha_pago->format('H:i') }}
                            @endif
                            y tu servicio ya quedó activo. Abajo está el detalle de la compra.
                        </p>

                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#ecfdf5; border-left:4px solid #1fca9d; border-radius:6px; margin:0 0 24px;">
                            <tr>
                                <td style="padding:14px 16px;">
                                    <p style="margin:0; font-size:13px; color:#047857; text-transform:uppercase; letter-spacing:.5px;">Monto pagado</p>
                                    <p style="margin:4px 0 0; font-size:26px; font-weight:bold; color:#065f46;">
                                        {{ $moneda }} {{ number_format((float) $pago->monto, 2, ',', '.') }}
                                    </p>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>

                {{-- Detalle del servicio --}}
                <tr>
                    <td style="padding:0 32px;">
                        <h2 style="margin:0 0 10px; font-family:Poppins, Arial, Helvetica, sans-serif; font-size:16px; color:#351f63;">
                            Detalle del servicio
                        </h2>

                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="font-size:14px; border-collapse:collapse;">
                            @if ($plan)
                                <tr>
                                    <td style="padding:9px 0; color:#6b7280; border-bottom:1px solid #f3f4f6;">Plan</td>
                                    <td style="padding:9px 0; text-align:right; font-weight:bold; border-bottom:1px solid #f3f4f6;">{{ $plan->nombre }}</td>
                                </tr>
                            @endif

                            @if ($suscripcion)
                                <tr>
                                    <td style="padding:9px 0; color:#6b7280; border-bottom:1px solid #f3f4f6;">Servicio</td>
                                    <td style="padding:9px 0; text-align:right; font-weight:bold; border-bottom:1px solid #f3f4f6;">{{ $suscripcion->tipo_servicio }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:9px 0; color:#6b7280; border-bottom:1px solid #f3f4f6;">Vigencia</td>
                                    <td style="padding:9px 0; text-align:right; font-weight:bold; border-bottom:1px solid #f3f4f6;">
                                        {{ $suscripcion->fecha_inicio?->format('d/m/Y') }} &mdash; {{ $suscripcion->fecha_fin?->format('d/m/Y') }}
                                    </td>
                                </tr>
                            @endif

                            @foreach ($dominios as $dominio)
                                <tr>
                                    <td style="padding:9px 0; color:#6b7280; border-bottom:1px solid #f3f4f6;">Dominio</td>
                                    <td style="padding:9px 0; text-align:right; font-weight:bold; border-bottom:1px solid #f3f4f6;">
                                        {{ $dominio->nombre }}
                                        <span style="display:block; font-weight:normal; font-size:12px; color:#6b7280;">
                                            hasta el {{ $dominio->fecha_fin?->format('d/m/Y') }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </table>
                    </td>
                </tr>

                {{-- Comprobante --}}
                <tr>
                    <td style="padding:24px 32px 0;">
                        <h2 style="margin:0 0 10px; font-family:Poppins, Arial, Helvetica, sans-serif; font-size:16px; color:#351f63;">
                            Comprobante
                        </h2>

                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="font-size:14px; border-collapse:collapse;">
                            <tr>
                                <td style="padding:9px 0; color:#6b7280; border-bottom:1px solid #f3f4f6;">N.º de pago</td>
                                <td style="padding:9px 0; text-align:right; font-weight:bold; border-bottom:1px solid #f3f4f6;">#{{ $pago->id }}</td>
                            </tr>
                            <tr>
                                <td style="padding:9px 0; color:#6b7280; border-bottom:1px solid #f3f4f6;">Forma de pago</td>
                                <td style="padding:9px 0; text-align:right; font-weight:bold; border-bottom:1px solid #f3f4f6;">{{ $pago->metodo_pago }}</td>
                            </tr>
                            @if (filled($pago->additional_data['bnb_voucher_id'] ?? null))
                                <tr>
                                    <td style="padding:9px 0; color:#6b7280; border-bottom:1px solid #f3f4f6;">Comprobante del banco</td>
                                    <td style="padding:9px 0; text-align:right; font-weight:bold; border-bottom:1px solid #f3f4f6;">{{ $pago->additional_data['bnb_voucher_id'] }}</td>
                                </tr>
                            @endif
                        </table>
                    </td>
                </tr>

                {{-- Pie --}}
                <tr>
                    <td style="padding:28px 32px 32px;">
                        <p style="margin:0 0 6px; font-size:14px; line-height:1.6; color:#374151;">
                            Guarda este correo como respaldo de tu compra. Si algo no coincide,
                            respóndenos indicando el número de pago.
                        </p>
                        <p style="margin:18px 0 0; font-size:12px; color:#9ca3af;">
                            Este es un mensaje automático de {{ $empresa }}.
                        </p>
                    </td>
                </tr>

            </table>

        </td>
    </tr>
</table>

</body>
</html>
