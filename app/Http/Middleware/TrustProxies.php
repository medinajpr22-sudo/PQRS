<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustProxies as Middleware;

/**
 * Proxies de confianza (config/pqrs.php → trusted_proxies, TRUSTED_PROXIES
 * en el .env).
 *
 * Detrás de nginx, un balanceador o Cloudflare, la conexión llega desde el
 * proxy: sin esto, todos los ciudadanos parecerían la misma IP y
 * compartirían los límites de intentos (radicar, consultar, entrar), y la
 * app no sabría que la conexión original era HTTPS.
 *
 * Se lee en cada petición (y no en bootstrap/app.php) para que funcione con
 * «php artisan config:cache».
 */
class TrustProxies extends Middleware
{
    /**
     * @return array<int, string>|string|null
     */
    protected function proxies()
    {
        $configured = trim((string) config('pqrs.trusted_proxies'));

        if ($configured === '') {
            return parent::proxies();
        }

        return $configured === '*'
            ? '*'
            : array_values(array_filter(array_map('trim', explode(',', $configured))));
    }
}
