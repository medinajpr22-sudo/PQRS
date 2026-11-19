<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Festivos
    |--------------------------------------------------------------------------
    |
    | Los festivos de Colombia se calculan solos para cualquier año
    | (App\Support\ColombianHolidays). En "holidays" puedes agregar días
    | extra (Y-m-d) que tampoco deben contar como hábiles, por ejemplo un
    | día cívico decretado.
    |
    */
    'colombian_holidays' => env('PQRS_COLOMBIAN_HOLIDAYS', true),

    'holidays' => [
        // '2026-12-24',
    ],

    /*
    |--------------------------------------------------------------------------
    | Adjuntos
    |--------------------------------------------------------------------------
    */
    'attachments' => [
        'max_kb' => 10240,
        'mimes' => ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx', 'xls', 'xlsx'],
    ],

    /*
    |--------------------------------------------------------------------------
    | «No soy un robot» (ALTCHA)
    |--------------------------------------------------------------------------
    |
    | El formulario de radicación pide marcar «No soy un robot». Al marcarla,
    | el navegador resuelve un pequeño cálculo (prueba de trabajo) que para
    | una persona tarda un par de segundos, pero que encarece enviar PQRS en
    | masa. Funciona en el propio servidor: no usa Google ni otros servicios
    | externos y no guarda cookies ni datos del ciudadano.
    |
    | "cost" son las iteraciones de cada intento y "counter" el rango de
    | intentos que necesita el navegador: subirlos lo hace más difícil.
    |
    */
    'captcha' => [
        'enabled' => env('PQRS_CAPTCHA', true),
        'cost' => (int) env('PQRS_CAPTCHA_COST', 1000),
        'counter' => [
            'min' => (int) env('PQRS_CAPTCHA_COUNTER_MIN', 1000),
            'max' => (int) env('PQRS_CAPTCHA_COUNTER_MAX', 3000),
        ],
        // Minutos que dura una verificación antes de tener que repetirla.
        'expires_minutes' => 20,
    ],
];
