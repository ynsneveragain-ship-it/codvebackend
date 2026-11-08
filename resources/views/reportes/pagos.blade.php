<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Reporte de Pagos</title>
    {{-- Dompdf no soporta flexbox ni grid: todo el layout va con tablas --}}
    <style>
        @page { margin: 28px 32px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #1f2937; }

        .encabezado { border-bottom: 2px solid #2563eb; padding-bottom: 9px; margin-bottom: 16px; }
        .encabezado h1 { margin: 0; font-size: 17px; color: #1e40af; }
        .encabezado p { margin: 4px 0 0; font-size: 9px; color: #6b7280; }

        h2 { font-size: 12px; color: #1e40af; margin: 16px 0 7px; }

        table { width: 100%; border-collapse: collapse; }

        .tarjetas td {
            width: 16.6%; text-align: center; padding: 9px 4px;
            border: 1px solid #e5e7eb; background: #f9fafb;
        }
        .tarjetas .numero { font-size: 17px; font-weight: bold; color: #1e40af; display: block; }
        .tarjetas .etiqueta { font-size: 8px; color: #6b7280; text-transform: uppercase; }

        .datos th { background: #1e40af; color: #fff; text-align: left; padding: 6px 7px; font-size: 9px; }
        .datos td { padding: 5px 7px; border-bottom: 1px solid #e5e7eb; }
        .datos tr:nth-child(even) td { background: #f9fafb; }

        .estado { padding: 2px 5px; border-radius: 3px; font-size: 8px; }
        .cobrado { background: #d1fae5; color: #065f46; }
        .pendiente { background: #fef3c7; color: #92400e; }

        .monto { font-weight: bold; text-align: right; }

        .vacio { text-align: center; color: #9ca3af; padding: 12px; }
        .pie { position: fixed; bottom: -10px; left: 0; right: 0;
               text-align: center; font-size: 8px; color: #9ca3af; }
    </style>
</head>
<body>

<div class="encabezado">
    <h1>Reporte de Pagos</h1>
    <p>Sistema de control de clientes y suscripciones &middot; Generado el {{ $r['generado_en'] }}</p>
</div>

<h2>Resumen general</h2>
<table class="tarjetas">
    <tr>
        <td><span class="numero">{{ $r['totales']['pagos'] }}</span><span class="etiqueta">Total</span></td>
        <td><span class="numero">{{ $r['totales']['cobrados'] }}</span><span class="etiqueta">Cobrados</span></td>
        <td><span class="numero">{{ $r['totales']['pendientes'] }}</span><span class="etiqueta">Pendientes</span></td>
        <td><span class="numero">{{ $r['totales']['monto_cobrado'] }}</span><span class="etiqueta">Monto cobrado</span></td>
        <td><span class="numero">{{ $r['totales']['monto_pendiente'] }}</span><span class="etiqueta">Monto pendiente</span></td>
        <td><span class="numero">{{ $r['totales']['con_qr'] }}</span><span class="etiqueta">Con QR del banco</span></td>
    </tr>
</table>

<h2>Por método de pago</h2>
<table class="datos">
    <thead>
        <tr>
            <th>Método</th>
            <th>Pagos</th>
            <th>Monto registrado</th>
            <th>Monto cobrado</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($r['por_metodo'] as $m)
            <tr>
                <td><strong>{{ $m['metodo'] }}</strong></td>
                <td>{{ $m['pagos'] }}</td>
                <td class="monto">{{ $m['monto'] }}</td>
                <td class="monto">{{ $m['cobrado'] }}</td>
            </tr>
        @empty
            <tr><td colspan="4" class="vacio">No hay pagos registrados</td></tr>
        @endforelse
    </tbody>
</table>

<h2>Detalle de pagos</h2>
<table class="datos">
    <thead>
        <tr>
            <th>#</th>
            <th>Cliente</th>
            <th>Susc.</th>
            <th>Servicio</th>
            <th>Fecha</th>
            <th>Método</th>
            <th>Monto</th>
            <th>QR</th>
            <th>Comprobante</th>
            <th>Estado</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($r['pagos'] as $p)
            <tr>
                <td><strong>{{ $p['id'] }}</strong></td>
                <td>{{ $p['cliente'] ?: '-' }}</td>
                <td>#{{ $p['suscripcion'] }}</td>
                <td>{{ $p['tipo_servicio'] ?: '-' }}</td>
                <td>{{ $p['fecha_pago'] }}</td>
                <td>{{ $p['metodo_pago'] }}</td>
                <td class="monto">{{ $p['monto'] }}</td>
                <td>{{ $p['qr_id'] ?: '-' }}</td>
                <td>{{ $p['voucher'] ?: '-' }}</td>
                <td>
                    <span class="estado {{ $p['cobrado'] ? 'cobrado' : 'pendiente' }}">
                        {{ ucfirst($p['estado']) }}
                    </span>
                </td>
            </tr>
        @empty
            <tr><td colspan="10" class="vacio">No hay pagos registrados</td></tr>
        @endforelse
    </tbody>
</table>

<div class="pie">Reporte generado automáticamente por el sistema</div>

</body>
</html>
