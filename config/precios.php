<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Parámetros de precio de los dominios
    |--------------------------------------------------------------------------
    |
    | Porkbun cotiza en USD. Estos dos valores convierten ese precio al importe
    | en moneda local y fijan lo que se le cobra al cliente:
    |
    |     precio_real (costo para la empresa) = precio USD * tipo_de_cambio
    |     precio      (lo que paga el cliente) = precio_real + margen_sobre_costo
    |
    | Viven en el BACKEND a propósito. Antes estaban en el navegador, y en
    | cuanto estos importes se guardan en la base de datos, dejar que el
    | navegador los dicte permitiría registrar una venta con el precio alterado.
    | El servidor los calcula y no acepta ningún importe que venga del cliente.
    |
    */

    'tipo_de_cambio' => (float) env('TIPO_DE_CAMBIO', 10.0),

    'margen_sobre_costo' => (float) env('MARGEN_SOBRE_COSTO', 20),

];
