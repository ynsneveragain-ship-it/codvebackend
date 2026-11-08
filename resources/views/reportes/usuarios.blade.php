<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Reporte de Usuarios</title>
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
            width: 20%; text-align: center; padding: 10px 4px;
            border: 1px solid #e5e7eb; background: #f9fafb;
        }
        .tarjetas .numero { font-size: 18px; font-weight: bold; color: #1e40af; display: block; }
        .tarjetas .etiqueta { font-size: 9px; color: #6b7280; text-transform: uppercase; }

        .datos th { background: #1e40af; color: #fff; text-align: left; padding: 7px 8px; font-size: 10px; }
        .datos td { padding: 6px 8px; border-bottom: 1px solid #e5e7eb; }
        .datos tr:nth-child(even) td { background: #f9fafb; }

        .estado { padding: 2px 6px; border-radius: 3px; font-size: 9px; }
        .activo { background: #d1fae5; color: #065f46; }
        .inactivo { background: #fee2e2; color: #991b1b; }

        .vacio { text-align: center; color: #9ca3af; padding: 14px; }
        .pie { position: fixed; bottom: -12px; left: 0; right: 0;
               text-align: center; font-size: 9px; color: #9ca3af; }
    </style>
</head>
<body>

<div class="encabezado">
    <h1>Reporte de Usuarios</h1>
    <p>Sistema de control de clientes y suscripciones &middot; Generado el {{ $r['generado_en'] }}</p>
</div>

<h2>Resumen general</h2>
<table class="tarjetas">
    <tr>
        <td><span class="numero">{{ $r['totales']['usuarios'] }}</span><span class="etiqueta">Total</span></td>
        <td><span class="numero">{{ $r['totales']['activos'] }}</span><span class="etiqueta">Activos</span></td>
        <td><span class="numero">{{ $r['totales']['inactivos'] }}</span><span class="etiqueta">Inactivos</span></td>
        <td><span class="numero">{{ $r['totales']['con_acceso'] }}</span><span class="etiqueta">Con acceso</span></td>
        <td><span class="numero">{{ $r['totales']['nunca_ingresaron'] }}</span><span class="etiqueta">Sin ingresar</span></td>
    </tr>
</table>

<h2>Distribución por rol</h2>
<table class="datos">
    <thead>
        <tr><th style="width:70%">Rol</th><th>Usuarios</th></tr>
    </thead>
    <tbody>
        @forelse ($r['por_rol'] as $fila)
            <tr>
                <td>{{ $fila['nombre'] }}</td>
                <td>{{ $fila['total'] }}</td>
            </tr>
        @empty
            <tr><td colspan="2" class="vacio">No hay roles registrados</td></tr>
        @endforelse
    </tbody>
</table>

<h2>Detalle de usuarios</h2>
<table class="datos">
    <thead>
        <tr>
            <th>Nombre</th>
            <th>Correo</th>
            <th>Teléfono</th>
            <th>Rol</th>
            <th>Estado</th>
            <th>Último acceso</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($r['usuarios'] as $u)
            <tr>
                <td>{{ $u['nombre'] }}</td>
                <td>{{ $u['correo'] }}</td>
                <td>{{ $u['telefono'] ?: '-' }}</td>
                <td>{{ $u['rol'] }}</td>
                <td>
                    <span class="estado {{ $u['activo'] ? 'activo' : 'inactivo' }}">
                        {{ $u['activo'] ? 'Activo' : 'Inactivo' }}
                    </span>
                </td>
                <td>{{ $u['ultimo_login'] ?: 'Nunca' }}</td>
            </tr>
        @empty
            <tr><td colspan="6" class="vacio">No hay usuarios registrados</td></tr>
        @endforelse
    </tbody>
</table>

<div class="pie">Reporte generado automáticamente por el sistema</div>

</body>
</html>
