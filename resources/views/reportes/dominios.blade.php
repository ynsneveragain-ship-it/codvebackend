<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Reporte de Dominios</title>
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
        .vigente { background: #d1fae5; color: #065f46; }
        .vencido { background: #fee2e2; color: #991b1b; }

        .margen { color: #065f46; font-weight: bold; }

        .vacio { text-align: center; color: #9ca3af; padding: 12px; }
        .pie { position: fixed; bottom: -10px; left: 0; right: 0;
               text-align: center; font-size: 8px; color: #9ca3af; }
    </style>
</head>
<body>

<div class="encabezado">
    <h1>Reporte de Dominios</h1>
    <p>Sistema de control de clientes y suscripciones &middot; Generado el {{ $r['generado_en'] }}</p>
</div>

<h2>Resumen general</h2>
<table class="tarjetas">
    <tr>
        <td><span class="numero">{{ $r['totales']['dominios'] }}</span><span class="etiqueta">Total</span></td>
        <td><span class="numero">{{ $r['totales']['vigentes'] }}</span><span class="etiqueta">Vigentes</span></td>
        <td><span class="numero">{{ $r['totales']['por_vencer'] }}</span><span class="etiqueta">Por vencer (30 d)</span></td>
        <td><span class="numero">{{ $r['totales']['vencidos'] }}</span><span class="etiqueta">Vencidos</span></td>
        <td><span class="numero">{{ $r['totales']['proveedores'] }}</span><span class="etiqueta">Proveedores</span></td>
        <td><span class="numero">{{ $r['totales']['margen_total'] }}</span><span class="etiqueta">Margen total</span></td>
    </tr>
</table>

<h2>Detalle de dominios</h2>
<table class="datos">
    <thead>
        <tr>
            <th>Dominio</th>
            <th>Proveedor</th>
            <th>Cliente</th>
            <th>Suscripción</th>
            <th>Precio real</th>
            <th>Precio</th>
            <th>Margen</th>
            <th>Inicio</th>
            <th>Fin</th>
            <th>Estado</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($r['dominios'] as $d)
            <tr>
                <td><strong>{{ $d['nombre'] }}</strong></td>
                <td>{{ $d['proveedor'] }}</td>
                <td>{{ $d['cliente'] ?: '-' }}</td>
                <td>{{ $d['suscripcion'] ?: '-' }}</td>
                <td>{{ $d['precio_real'] }}</td>
                <td>{{ $d['precio'] }}</td>
                <td class="margen">{{ $d['margen'] }}</td>
                <td>{{ $d['fecha_inicio'] }}</td>
                <td>{{ $d['fecha_fin'] }}</td>
                <td>
                    <span class="estado {{ $d['vigente'] ? 'vigente' : 'vencido' }}">
                        {{ $d['vigente'] ? 'Vigente' : 'Vencido' }}
                    </span>
                </td>
            </tr>
        @empty
            <tr><td colspan="10" class="vacio">No hay dominios registrados</td></tr>
        @endforelse
    </tbody>
</table>

<div class="pie">Reporte generado automáticamente por el sistema</div>

</body>
</html>
