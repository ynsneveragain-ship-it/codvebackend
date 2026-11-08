<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Extensiones que Porkbun NO vende
    |--------------------------------------------------------------------------
    |
    | Cuando un dominio termina en una de estas, el sistema NO llama a Porkbun:
    | responde directamente que no está disponible y pasa a buscar alternativas.
    | Así se ahorra una consulta que de todos modos devolvería INVALID_TLD.
    |
    | EDITA ESTA LISTA si Porkbun empieza a vender alguna (quítala de aquí) o
    | deja de vender otra (añádela). Es el único sitio que hay que tocar.
    |
    | Cómo se comparan: por el FINAL del dominio. Poner 'bo' basta para cubrir
    | midominio.bo, midominio.com.bo, midominio.net.bo y midominio.org.bo.
    |
    | OJO con los casos mixtos: Porkbun SÍ vende '.co' pero NO vende '.com.co',
    | por eso 'com.co' está en la lista y 'co' no. Lo mismo al revés con México:
    | vende '.mx' y '.com.mx', así que ninguno de los dos aparece aquí.
    |
    | Esta lista NUNCA va a estar completa: Porkbun vende unas 913 extensiones
    | de las más de 1.500 que existen. Cubre las de la región y las más
    | probables; si se cuela otra, Porkbun responde INVALID_TLD y el sistema la
    | trata igual que si estuviera en esta lista.
    |
    | Comprobado contra el catálogo real de Porkbun el 2026-11-08
    | (endpoint /pricing/get, 913 extensiones).
    |
    */

    'tlds_no_soportados' => [
        // Bolivia
        'bo',
        // Resto de Sudamérica
        'ar',   // Argentina
        'br',   // Brasil
        'cl',   // Chile
        'pe',   // Perú
        'ec',   // Ecuador
        'py',   // Paraguay
        'uy',   // Uruguay
        've',   // Venezuela
        // Colombia: '.co' sí se vende, '.com.co' no
        'com.co',
        // Centroamérica y Caribe
        'cr',   // Costa Rica
        'pa',   // Panamá
        'do',   // República Dominicana
        'gt',   // Guatemala
        'hn',   // Honduras
        'ni',   // Nicaragua
        'sv',   // El Salvador
        'cu',   // Cuba
        // Europa de habla hispana y portuguesa
        'es',   // España
        'pt',   // Portugal
        // Otras europeas que tampoco vende
        'fr',   // Francia
        'it',   // Italia
    ],

    /*
    |--------------------------------------------------------------------------
    | Extensiones que se ofrecen como alternativa
    |--------------------------------------------------------------------------
    |
    | Cuando el dominio pedido está ocupado (o su extensión no se vende), se
    | prueban estas con el mismo nombre y se muestran las que estén libres.
    | Se usan las primeras 'max_alternativas' que no sean la ya consultada.
    |
    | El orden importa: es el orden en que aparecen los botones.
    |
    */

    'tlds_alternativas' => ['com', 'net', 'org', 'io', 'co', 'online', 'app'],

    'max_alternativas' => 5,

];
