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

    /*
    |--------------------------------------------------------------------------
    | Super administrador inicial (php artisan db:seed)
    |--------------------------------------------------------------------------
    |
    | En producción la contraseña es obligatoria: el seeder no crea un
    | usuario con una contraseña conocida.
    |
    */
    'super_admin' => [
        'email' => env('PQRS_SUPER_ADMIN_EMAIL', 'admin@pqrs.test'),
        'password' => env('PQRS_SUPER_ADMIN_PASSWORD'),
    ],

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
    | Envío de correos
    |--------------------------------------------------------------------------
    |
    | Todas las empresas envían desde el mismo buzón (MAIL_USERNAME), así que
    | estos topes son de ese buzón. Si se pasa de lo que permite el
    | proveedor, el proveedor lo bloquea. Por eso, los correos que superan el
    | tope esperan su turno en la cola.
    |
    |   - Microsoft 365 (correo corporativo en Outlook): 30 correos por minuto
    |     y 10 000 destinatarios al día.
    |   - Outlook.com / Hotmail gratuito: unos 300 al día, menos en cuentas
    |     nuevas (Microsoft no publica una cifra fija).
    |   - Gmail gratuito: 500 al día.
    |
    | "per_day" en 0 no pone tope diario. Con una cuenta gratuita conviene
    | dejarlo un poco por debajo del límite del proveedor.
    |
    | "acks_per_address" frena que un robot llene el buzón de otra persona
    | radicando con su correo: pasado ese número de acuses en un día, la
    | PQRS se radica igual (el número se ve en pantalla), pero sin correo.
    |
    | "check_dns" rechaza correos cuyo dominio no existe o no recibe correos
    | (p. ej. «gmial.com»). Cada correo que rebota le resta reputación al
    | buzón que envía.
    |
    */
    'mail' => [
        // Adjuntos de la respuesta que caben en el correo; si pesan más, el
        // ciudadano los descarga al consultar su radicado. Microsoft Graph
        // acepta correos de hasta 4 MB en total.
        'max_attachments_kb' => (int) env('PQRS_MAIL_MAX_ATTACHMENTS_KB', env('MAIL_MAILER') === 'microsoft-graph' ? 2048 : 10240),
        'per_minute' => (int) env('PQRS_MAIL_PER_MINUTE', 20),
        'per_day' => (int) env('PQRS_MAIL_PER_DAY', 0),
        'acks_per_address' => (int) env('PQRS_MAIL_ACKS_PER_ADDRESS', 10),
        'check_dns' => (bool) env('PQRS_MAIL_CHECK_DNS', true),
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
