<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\RolController;
use App\Http\Controllers\PermisoController;
use App\Http\Controllers\ClienteController;
use App\Http\Controllers\PlanController;
use App\Http\Controllers\DominioController;
use App\Http\Controllers\CompraController;
use App\Http\Controllers\PagoQrController;
use App\Http\Controllers\SuscripcionController;
use App\Http\Controllers\PagoController;
use App\Http\Controllers\VencimientoController;

// Rutas públicas (sin autenticación)
Route::group(['prefix' => 'auth'], function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
});

// Notificacion de pago del BNB (el webhook ReceiveNotification de su API).
// Es publica porque la llama el banco, que no tiene ningun token nuestro: lo
// unico que la protege es el secreto del final de la URL acordada con ellos
// (BNB_WEBHOOK_TOKEN). En local no la recibe nadie, el backend no es publico;
// ahi la confirmacion del pago llega por GET /api/pagos/{pago}/qr.
Route::post('/bnb/notificacion/{token?}', [PagoQrController::class, 'notificacion']);

// Rutas protegidas (requieren token Sanctum)
Route::middleware('auth:sanctum')->group(function () {
    // Auth
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me', [AuthController::class, 'me']);

    // REPORTES. Van todos ANTES de los apiResource: si no, '{recurso}/{id}'
    // capturaria "reporte" como si fuera un id y devolveria 404.
    Route::get('/users/reporte', [UserController::class, 'reporte']);
    Route::get('/users/reporte/pdf', [UserController::class, 'reportePdf']);

    Route::get('/roles/reporte', [RolController::class, 'reporte']);
    Route::get('/roles/reporte/pdf', [RolController::class, 'reportePdf']);

    Route::get('/permisos/reporte', [PermisoController::class, 'reporte']);
    Route::get('/permisos/reporte/pdf', [PermisoController::class, 'reportePdf']);

    Route::get('/clientes/reporte', [ClienteController::class, 'reporte']);
    Route::get('/clientes/reporte/pdf', [ClienteController::class, 'reportePdf']);

    Route::get('/planes/reporte', [PlanController::class, 'reporte']);
    Route::get('/planes/reporte/pdf', [PlanController::class, 'reportePdf']);

    Route::get('/dominios/reporte', [DominioController::class, 'reporte']);
    Route::get('/dominios/reporte/pdf', [DominioController::class, 'reportePdf']);

    Route::get('/suscripciones/reporte', [SuscripcionController::class, 'reporte']);
    Route::get('/suscripciones/reporte/pdf', [SuscripcionController::class, 'reportePdf']);

    Route::get('/pagos/reporte', [PagoController::class, 'reporte']);
    Route::get('/pagos/reporte/pdf', [PagoController::class, 'reportePdf']);

    // Disponibilidad en Porkbun. Va aqui arriba por el mismo motivo que los
    // reportes: por delante del apiResource de dominios.
    Route::post('/dominios/verificar', [DominioController::class, 'verificar']);

    // Registro de una venta completa: cliente + suscripcion + dominio + pago
    // en una sola transaccion. No es un CRUD, de ahi que no haya apiResource.
    // La venta nace pendiente y la respuesta ya trae el QR de cobro.
    Route::post('/compras', [CompraController::class, 'store']);

    // Lo que esta a punto de caducar (dominios + suscripciones), para la
    // campana del dashboard. Solo lectura, no es un CRUD.
    Route::get('/vencimientos', [VencimientoController::class, 'index']);
    // Boton "Notificar" de cada fila del panel: le escribe al cliente avisando
    // de que su dominio o su suscripcion esta por vencer. Son POST porque
    // mandan un correo (no son consultas), y van aqui y no en Dominios /
    // Suscripciones porque pertenecen al aviso, no al CRUD de cada entidad.
    Route::post('/vencimientos/dominios/{dominio}/notificar', [VencimientoController::class, 'notificarDominio']);
    Route::post('/vencimientos/suscripciones/{suscripcion}/notificar', [VencimientoController::class, 'notificarSuscripcion']);

    // Cobro por QR del BNB de un pago ya registrado. Tampoco es el CRUD de
    // Pagos (ese modulo sigue sin existir): solo pedir el QR, preguntar si ya
    // se pago y anularlo.
    Route::post('/pagos/{pago}/qr', [PagoQrController::class, 'generar']);
    Route::get('/pagos/{pago}/qr', [PagoQrController::class, 'estado']);
    Route::post('/pagos/{pago}/qr/cancelar', [PagoQrController::class, 'cancelar']);

    // Users CRUD
    Route::apiResource('users', UserController::class);

    // Roles CRUD
    Route::apiResource('roles', RolController::class)->parameters(['roles' => 'rol']);

    // Permisos CRUD
    Route::apiResource('permisos', PermisoController::class)->parameters(['permisos' => 'permiso']);

    // Relación rol <-> permiso (tabla pivote), desde cualquiera de los dos lados
    Route::put('/roles/{rol}/permisos', [RolController::class, 'syncPermisos']);
    Route::put('/permisos/{permiso}/roles', [PermisoController::class, 'syncRoles']);

    // Clientes CRUD
    Route::apiResource('clientes', ClienteController::class)->parameters(['clientes' => 'cliente']);

    // Planes CRUD ('planes' se singularizaria como "plane", de ahi el parametro explicito)
    Route::apiResource('planes', PlanController::class)->parameters(['planes' => 'plan']);

    // Dominios CRUD ('dominios' ya se singulariza bien, pero se deja explicito
    // como el resto para no depender del inflector)
    Route::apiResource('dominios', DominioController::class)->parameters(['dominios' => 'dominio']);

    // Suscripciones CRUD. El parametro explicito aqui NO es decorativo:
    // Laravel singulariza en ingles y 'suscripciones' no le da 'suscripcion',
    // asi que sin esto el modelo no se inyectaria.
    Route::apiResource('suscripciones', SuscripcionController::class)
        ->parameters(['suscripciones' => 'suscripcion']);

    // Pagos CRUD. Va DESPUES de las rutas /pagos/{pago}/qr de mas arriba, que
    // son del cobro por QR y no de este CRUD.
    Route::apiResource('pagos', PagoController::class)->parameters(['pagos' => 'pago']);
});
