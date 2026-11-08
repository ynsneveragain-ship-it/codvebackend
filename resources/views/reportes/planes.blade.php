<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Reporte de Planes</title>
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
        .datos td { padding: 5px 7px; border-bottom: 1px solid #e5e7eb; vertical-align: top; }
        .datos tr:nth-child(even) td { background: #f9fafb; }

        .estado { padding: 2px 5px; border-radius: 3px; font-size: 8px; }
        .contratado { background: #d1fae5; color: #065f46; }
        .libre { background: #fef3c7; color: #92400e; }

        .oferta { color: #047857; font-weight: bold; }
        .tachado { color: #9ca3af; text-decoration: line-through; font-size: 9px; }

        .caracteristica {
            display: inline-block; background: #dbeafe; color: #1e40af;
            padding: 2px 5px; border-radius: 3px; font-size: 8px; margin: 0 3px 3px 0;
        }
        .sin { color: #9ca3af; font-size: 9px; }

        .vacio { text-align: center; color: #9ca3af; padding: 12px; }
        .pie { position: fixed; bottom: -10px; left: 0; right: 0;
               text-align: center; font-size: 8px; color: #9ca3af; }
    </style>
</head>
<body>

<div class="encabezado">
    <h1>Reporte de Planes</h1>
    <p>Sistema de control de clientes y suscripciones &middot; Generado el {{ $r['generado_en'] }}</p>
</div>

<h2>Resumen general</h2>
<table class="tarjetas">
    <tr>
        <td><span class="numero">{{ $r['totales']['planes'] }}</span><span class="etiqueta">Total</span></td>
        <td><span class="numero">{{ $r['totales']['con_oferta'] }}</span><span class="etiqueta">Con oferta</span></td>
        <td><span class="numero">{{ $r['totales']['contratados'] }}</span><span class="etiqueta">Contratados</span></td>
        <td><span class="numero">{{ $r['totales']['sin_contratar'] }}</span><span class="etiqueta">Sin contratar</span></td>
        <td><span class="numero">{{ $r['totales']['precio_promedio'] }}</span><span class="etiqueta">Precio promedio</span></td>
    </tr>
</table>

<h2>Detalle de planes</h2>
<table class="datos">
    <thead>
        <tr>
            <th style="width:15%">Plan</th>
            <th style="width:10%">Vigencia</th>
            <th style="width:12%">Precio</th>
            <th style="width:12%">Estado</th>
            <th style="width:11%">Suscripciones</th>
            <th>Características</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($r['planes'] as $p)
            <tr>
                <td><strong>{{ $p['nombre'] }}</strong></td>
                <td>{{ $p['vigencia'] }} {{ $p['vigencia'] == 1 ? 'mes' : 'meses' }}</td>
                <td>
                    @if ($p['precio_oferta'])
                        <span class="oferta">{{ $p['precio_oferta'] }}</span><br>
                        <span class="tachado">{{ $p['precio'] }}</span>
                    @else
                        {{ $p['precio'] }}
                    @endif
                </td>
                <td>
                    <span class="estado {{ $p['contratado'] ? 'contratado' : 'libre' }}">
                        {{ $p['contratado'] ? 'Contratado' : 'Sin contratar' }}
                    </span>
                </td>
                <td>{{ $p['suscripciones'] }}</td>
                <td>
                    @forelse ($p['caracteristicas'] as $c)
                        <span class="caracteristica">{{ $c }}</span>
                    @empty
                        <span class="sin">Sin características</span>
                    @endforelse
                </td>
            </tr>
        @empty
            <tr><td colspan="6" class="vacio">No hay planes registrados</td></tr>
        @endforelse
    </tbody>
</table>

<div class="pie">Reporte generado automáticamente por el sistema</div>

</body>
</html>
