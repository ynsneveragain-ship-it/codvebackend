<?php

return [

    /*
    |--------------------------------------------------------------------------
    | API de Pago QR Simple del Banco Nacional de Bolivia
    |--------------------------------------------------------------------------
    |
    | La referencia de la API esta en BNB_QR_API.md (raiz del proyecto).
    |
    | Las credenciales NUNCA viajan al frontend: el navegador llama a nuestra
    | API y es Laravel quien habla con el banco, igual que con Porkbun.
    |
    | OJO con 'url': por defecto apunta al ambiente de PRUEBAS. La URL de
    | produccion hay que confirmarla con el banco (y usar https).
    |
    */

    'url' => env('BNB_API_URL', 'http://test.bnb.com.bo'),

    'account_id' => env('BNB_ACCOUNT_ID'),

    'authorization_id' => env('BNB_AUTHORIZATION_ID'),

    'timeout' => (int) env('BNB_TIMEOUT', 20),

    /*
    |--------------------------------------------------------------------------
    | Parametros del QR
    |--------------------------------------------------------------------------
    |
    | moneda          'BOB' o 'USD'
    | cuenta_destino  "1" = cuenta en moneda nacional, "2" = moneda extranjera
    | un_solo_uso     true = el QR sirve para un unico pago. Solo los QR de un
    |                 solo uso que no se hayan usado se pueden cancelar.
    | dias_vigencia   El banco solo acepta una FECHA de expiracion (sin hora),
    |                 asi que lo mas corto que se puede pedir es 1 dia.
    |
    */

    'moneda' => env('BNB_MONEDA', 'BOB'),

    'cuenta_destino' => env('BNB_CUENTA_DESTINO', '1'),

    'un_solo_uso' => filter_var(env('BNB_QR_UN_SOLO_USO', true), FILTER_VALIDATE_BOOLEAN),

    'dias_vigencia' => (int) env('BNB_DIAS_VIGENCIA_QR', 1),

    // Texto que el banco devuelve en la notificacion; lleva detras el id del pago
    'glosa_prefijo' => env('BNB_GLOSA_PREFIJO', 'Pago'),

    /*
    |--------------------------------------------------------------------------
    | Token
    |--------------------------------------------------------------------------
    |
    | El JWT se guarda en cache hasta un poco antes de su propio 'exp', para no
    | pedir uno nuevo en cada peticion. 'margen_segundos' es ese "poco antes";
    | 'ttl_por_defecto' se usa solo si el JWT no trae 'exp' legible.
    |
    */

    'cache_token' => 'bnb.token',

    'margen_segundos' => (int) env('BNB_MARGEN_TOKEN', 60),

    'ttl_por_defecto' => (int) env('BNB_TTL_TOKEN', 600),

    /*
    |--------------------------------------------------------------------------
    | Webhook ReceiveNotification
    |--------------------------------------------------------------------------
    |
    | El banco llama a una URL NUESTRA cuando alguien paga un QR. La URL se
    | acuerda con el banco y debe ser publica (en local no es accesible: ahi la
    | confirmacion llega preguntando el estado, ver PagoQrController).
    |
    | 'webhook_token' es un secreto propio, no del banco: si se define, la ruta
    | solo responde si la URL acordada lo lleva al final. Es lo unico que
    | impide que un tercero confirme pagos llamando a esa ruta, porque la API
    | del BNB no firma ni autentica la notificacion.
    |
    |     https://<dominio-empresa>/api/bnb/notificacion/<BNB_WEBHOOK_TOKEN>
    |
    */

    'webhook_token' => env('BNB_WEBHOOK_TOKEN'),

];
