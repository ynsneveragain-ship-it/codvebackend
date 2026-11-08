<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Aviso de vencimientos (la campana del dashboard)
    |--------------------------------------------------------------------------
    |
    | dias_aviso    Cuántos días por delante se miran. Todo lo que caduque de
    |               aquí a esa fecha entra en el aviso. Son los mismos 30 días
    |               que usan los reportes para su contador "por vencer".
    |
    | dias_urgente  Por debajo de cuántos días restantes el aviso pasa a rojo.
    |
    | OJO: lo YA VENCIDO también entra, con los días en negativo. Es a
    | propósito: si se filtrara, un dominio que caducó ayer desaparecería del
    | aviso justo cuando más hay que mirarlo. Al ser negativo cuenta siempre
    | como urgente (rojo).
    |
    */

    'dias_aviso' => (int) env('AVISO_VENCIMIENTO_DIAS', 30),

    'dias_urgente' => (int) env('AVISO_URGENTE_DIAS', 7),

    /*
    | Roles que NO pueden pulsar "Notificar" en el panel de vencimientos.
    | Ver la lista es inofensivo (solo mira); avisar al cliente manda un correo
    | a una persona real y no se puede deshacer, asi que 'visita' queda fuera,
    | igual que ya esta fuera de registrar ventas y cobrar por QR.
    */
    'roles_sin_notificar' => ['visita'],

    /*
    | Lo que se guarda en dominios.estado_notificacion y
    | suscripciones.estado_notificacion cuando el aviso sale de verdad. NULL
    | (nunca avisado) es el otro unico valor que el sistema entiende: el panel
    | pinta el boton "Notificar" solo con NULL, y el texto "Notificado" con
    | cualquier otra cosa.
    */
    'estado_notificado' => 'notificado',

];
