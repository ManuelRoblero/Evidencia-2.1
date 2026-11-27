<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Funciones por fase
    |--------------------------------------------------------------------------
    | Fase 1 (actual): aplicación interna para Administrador y Recepción.
    | Fase futura: tienda en línea y portal/página web para clientes.
    | Se activan poniendo true en el .env, sin borrar código.
    */
    'features' => [
        'store' => (bool) env('FEATURE_STORE', false),
        'client_portal' => (bool) env('FEATURE_CLIENT_PORTAL', false),
    ],

    // Días de vigencia de los créditos al registrar un pago de paquete en mostrador.
    'credit_validity_days' => (int) env('CREDIT_VALIDITY_DAYS', 30),
];
