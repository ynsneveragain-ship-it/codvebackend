<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Reporte de Clientes</title>
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
            width: 20%; text-align: center; padding: 9px 4px;
            border: 1px solid #e5e7eb; background: #f9fafb;
        }
        .tarjetas .numero { font-size: 17px; font-weight: bold; color: #1e40af; display: block; }
        .tarjetas .etiqueta { font-size: 8px; color: #6b7280; text-transform: uppercase; }

        .datos th { background: #1e40af; color: #fff; text-align: left; padding: 6px 7px; font-size: 9px; }
        .datos td { padding: 5px 7px; border-bottom: 1px solid #e5e7eb; }
        .datos tr:nth-child(even) td { background: #f9fafb; }

        .estado { padding: 2px 5px; border-radius: 3px; font-size: 8px; }
        .activo { background: #d1fae5; color: #065f46; }
        .inactivo { background: #fef3c7; color: #92400e; }

        .vacio { text-align: center; color: #9ca3af; padding: 12px; }
        .pie { position: fixed; bottom: -10px; left: 0; right: 0;
               text-align: center; font-size: 8px; color: #9ca3af; }
    </style>
</head>
<body>

<div class="encabezado">
    <h1>Reporte de Clientes</h1>
    <p>Sistema de control de clientes y suscripciones &middot; Generado el {{ $r['generado_en'] }}</p>
</div>

<h2>Resumen general</h2>
<table class="tarjetas">
    <tr>
        <td><span class="numero">{{ $r['totales']['clientes'] }}</span><span class="etiqueta">Total</span></td>
        <td><span class="numero">{{ $r['totales']['con_suscripcion'] }}</span><span class="etiqueta">Con suscripción</span></td>
        <td><span class="numero">{{ $r['totales']['sin_suscripcion'] }}</span><span class="etiqueta">Sin suscripción</span></td>
        <td><span class="numero">{{ $r['totales']['con_correo'] }}</span><span class="etiqueta">Con correo</span></td>
        <td><span class="numero">{{ $r['totales']['con_telefono'] }}</span><span class="etiqueta">Con teléfono</span></td>
    </tr>
</table>

<h2>Detalle de clientes</h2>
<table class="datos">
    <thead>
        <tr>
            <th>Nombre</th>
            <th>Razón social</th>
            <th>CI / NIT</th>
            <th>Correo</th>
            <th>Teléfono</th>
            <th>Estado</th>
            <th>Suscripciones</th>
            <th>Pagos</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($r['clientes'] as $c)
            <tr>
                <td><strong>{{ $c['nombre'] }}</strong></td>
                <td>{{ $c['razon_social'] ?: '-' }}</td>
                <td>{{ $c['numero_documento'] }}</td>
                <td>{{ $c['correo'] ?: '-' }}</td>
                <td>{{ $c['telefono'] ?: '-' }}</td>
                <td>
                    <span class="estado {{ $c['activo'] ? 'activo' : 'inactivo' }}">
                        {{ $c['activo'] ? 'Activo' : 'Sin suscripción' }}
                    </span>
                </td>
                <td>{{ $c['suscripciones'] }}</td>
                <td>{{ $c['pagos'] }}</td>
            </tr>
        @empty
            <tr><td colspan="8" class="vacio">No hay clientes registrados</td></tr>
        @endforelse
    </tbody>
</table>

<div class="pie">Reporte generado automáticamente por el sistema</div>

</body>
</html>
