<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Reporte de Permisos</title>
    {{-- Dompdf no soporta flexbox ni grid: todo el layout va con tablas --}}
    <style>
        @page { margin: 30px 35px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1f2937; }

        .encabezado { border-bottom: 2px solid #2563eb; padding-bottom: 10px; margin-bottom: 18px; }
        .encabezado h1 { margin: 0; font-size: 18px; color: #1e40af; }
        .encabezado p { margin: 4px 0 0; font-size: 10px; color: #6b7280; }

        h2 { font-size: 13px; color: #1e40af; margin: 20px 0 8px; }

        table { width: 100%; border-collapse: collapse; }

        .tarjetas td {
            width: 33.33%; text-align: center; padding: 10px 4px;
            border: 1px solid #e5e7eb; background: #f9fafb;
        }
        .tarjetas .numero { font-size: 18px; font-weight: bold; color: #1e40af; display: block; }
        .tarjetas .etiqueta { font-size: 9px; color: #6b7280; text-transform: uppercase; }

        .datos th { background: #1e40af; color: #fff; text-align: left; padding: 7px 8px; font-size: 10px; }
        .datos td { padding: 6px 8px; border-bottom: 1px solid #e5e7eb; vertical-align: top; }
        .datos tr:nth-child(even) td { background: #f9fafb; }

        .estado { padding: 2px 6px; border-radius: 3px; font-size: 9px; }
        .asignado { background: #d1fae5; color: #065f46; }
        .huerfano { background: #fee2e2; color: #991b1b; }

        .etiqueta-rol {
            display: inline-block; background: #ede9fe; color: #5b21b6;
            padding: 2px 6px; border-radius: 3px; font-size: 9px; margin: 0 3px 3px 0;
        }
        .sin { color: #9ca3af; font-size: 10px; }

        .vacio { text-align: center; color: #9ca3af; padding: 14px; }
        .pie { position: fixed; bottom: -12px; left: 0; right: 0;
               text-align: center; font-size: 9px; color: #9ca3af; }
    </style>
</head>
<body>

<div class="encabezado">
    <h1>Reporte de Permisos</h1>
    <p>Sistema de control de clientes y suscripciones &middot; Generado el {{ $r['generado_en'] }}</p>
</div>

<h2>Resumen general</h2>
<table class="tarjetas">
    <tr>
        <td><span class="numero">{{ $r['totales']['permisos'] }}</span><span class="etiqueta">Total</span></td>
        <td><span class="numero">{{ $r['totales']['asignados'] }}</span><span class="etiqueta">Asignados</span></td>
        <td><span class="numero">{{ $r['totales']['sin_asignar'] }}</span><span class="etiqueta">Sin asignar</span></td>
    </tr>
</table>

<h2>Detalle de permisos</h2>
<table class="datos">
    <thead>
        <tr>
            <th style="width:20%">Permiso</th>
            <th style="width:25%">Descripción</th>
            <th style="width:12%">Estado</th>
            <th style="width:8%">N° roles</th>
            <th>Roles que lo tienen</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($r['permisos'] as $p)
            <tr>
                <td><strong>{{ $p['nombre'] }}</strong></td>
                <td>{{ $p['descripcion'] ?: '-' }}</td>
                <td>
                    <span class="estado {{ $p['asignado'] ? 'asignado' : 'huerfano' }}">
                        {{ $p['asignado'] ? 'Asignado' : 'Sin asignar' }}
                    </span>
                </td>
                <td>{{ $p['total_roles'] }}</td>
                <td>
                    @forelse ($p['roles'] as $rol)
                        <span class="etiqueta-rol">{{ $rol }}</span>
                    @empty
                        <span class="sin">Ningún rol lo tiene</span>
                    @endforelse
                </td>
            </tr>
        @empty
            <tr><td colspan="5" class="vacio">No hay permisos registrados</td></tr>
        @endforelse
    </tbody>
</table>

<div class="pie">Reporte generado automáticamente por el sistema</div>

</body>
</html>
