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
];
