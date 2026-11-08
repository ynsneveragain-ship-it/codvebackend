<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Vocabulario de una venta
    |--------------------------------------------------------------------------
    |
    | Las columnas tipo_servicio, estado y metodo_pago son texto libre en la
    | base de datos. Esta lista es la que cierra los valores admitidos, y es
    | editable sin tocar código.
    |
    */

    // El asistente de compra NO pregunta el tipo de servicio: lo deduce el
    // servidor segun la venta lleve dominio o no. Estos son los dos unicos
    // valores que el sistema genera.
    'tipo_sin_dominio' => 'Hosting',
    'tipo_con_dominio' => 'Hosting + Dominio',

    // Metodo con el que se guardan los cobros del asistente de compra. Es el
    // unico que este genera: el pago va siempre por QR del BNB.
    'metodo_bnb_qr' => 'QR',

    // Vocabulario completo de la columna metodo_pago, para el CRUD de Pagos
    // que todavia no existe (un cobro cargado a mano si puede ser en efectivo
    // o por transferencia). El asistente de compra ya NO usa esta lista.
    'metodos_pago' => ['Efectivo', 'Transferencia', 'QR', 'Tarjeta'],

    'proveedor_dominio' => 'Porkbun',

    // Los dominios se registran y renuevan por años, al margen de lo que dure
    // el plan contratado
    'vigencia_dominio_anios' => 1,

    // Roles que no pueden registrar ventas
    'roles_sin_venta' => ['visita'],

    'estados' => [
        'suscripcion_activa' => 'activo',
        'suscripcion_pendiente' => 'pendiente',
        'pago_pagado' => 'pagado',
        'pago_pendiente' => 'pendiente',
    ],

    // Valores que admiten los CRUD de Suscripciones y Pagos. Son exactamente
    // los de arriba y ni uno mas: añadir aqui un estado nuevo no basta, hay
    // que mirar quien lo lee (el cobro por QR solo entiende estos, y daria
    // por no cobrada cualquier otra cosa).
    'estados_suscripcion' => ['activo', 'pendiente'],

    'estados_pago' => ['pagado', 'pendiente'],

];
