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
    | Proxies de confianza
    |--------------------------------------------------------------------------
    |
    | Si el servidor está detrás de nginx (como proxy), un balanceador o
    | Cloudflare: sus IP separadas por coma, o * si al servidor solo se llega
    | a través del proxy. Sin esto, todos los ciudadanos parecen una sola IP
    | y comparten los límites de intentos. Vacío = conexión directa.
    |
    */
    'trusted_proxies' => env('TRUSTED_PROXIES', ''),

    /*
    |--------------------------------------------------------------------------
    | Adjuntos
    |--------------------------------------------------------------------------
    |
    | Sin .doc ni .xls: esos formatos viejos de Office pueden traer macros, y
    | es una de las formas más comunes de meter un virus por un portal que
    | recibe archivos del público. Laravel revisa el contenido real del
    | archivo, así que un .doc renombrado a .docx también se rechaza.
    |
    | En producción conviene además escanear los adjuntos con un antivirus
    | (por ejemplo ClamAV en el servidor).
    |
    */
    'attachments' => [
        // Tope por archivo y por envío (todos los archivos juntos), en KB.
        // Valen para el formulario del ciudadano y para las respuestas del
        // equipo. El servidor debe permitir al menos eso: en php.ini,
        // upload_max_filesize ≥ max_kb y post_max_size ≥ max_total_kb + 1 MB
        // (y en nginx, client_max_body_size). Si PHP permite menos, la app
        // usa el límite de PHP para no aceptar archivos que se perderían
        // (ver App\Support\UploadLimits).
        'max_kb' => (int) env('PQRS_ATTACHMENTS_MAX_KB', 5120),
        'max_total_kb' => (int) env('PQRS_ATTACHMENTS_MAX_TOTAL_KB', 10240),
        'mimes' => ['pdf', 'jpg', 'jpeg', 'png', 'docx', 'xlsx'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Cabeceras de seguridad (App\Http\Middleware\SecurityHeaders)
    |--------------------------------------------------------------------------
    |
    | "csp": "enforce" bloquea lo que no cumpla la política (producción),
    | "report" solo avisa en la consola del navegador (desarrollo) y "off"
    | no la envía.
    |
    */
    'security' => [
        'csp' => env('PQRS_CSP', env('APP_ENV') === 'production' ? 'enforce' : 'report'),
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
        // Adjuntos de la respuesta que caben en el correo, contando el logo
        // de la empresa; si pesan más, el ciudadano los descarga al consultar
        // su radicado. Microsoft Graph acepta correos de hasta 4 MB, pero
        // los archivos viajan codificados dos veces (casi el doble de su
        // peso): 1,8 MB de archivos ya ocupan unos 3,4 MB.
        'max_attachments_kb' => (int) env('PQRS_MAIL_MAX_ATTACHMENTS_KB', env('MAIL_MAILER') === 'microsoft-graph' ? 1800 : 10240),
        'per_minute' => (int) env('PQRS_MAIL_PER_MINUTE', 20),
        'per_day' => (int) env('PQRS_MAIL_PER_DAY', 0),
        'acks_per_address' => (int) env('PQRS_MAIL_ACKS_PER_ADDRESS', 3),
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
