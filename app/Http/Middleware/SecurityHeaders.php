<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cabeceras de seguridad del navegador, en todas las respuestas.
 *
 * - Nadie puede mostrar la app dentro de un marco de otra página
 *   (clickjacking): frame-ancestors 'none' y X-Frame-Options.
 * - Content-Security-Policy: el navegador solo ejecuta los scripts de la
 *   propia app (con un «nonce» distinto en cada página). Si algún día se
 *   cuela un script por un hueco XSS, no corre.
 * - El navegador no adivina tipos de archivo (nosniff): un adjunto «.png»
 *   que en realidad es HTML no se ejecuta como página.
 * - Con HTTPS, el navegador recuerda usar siempre HTTPS (HSTS).
 *
 * En desarrollo la política se envía en modo «solo reportar»: avisa en la
 * consola del navegador sin bloquear (Vite y las herramientas de
 * desarrollo usan scripts propios). Ver config/pqrs.php → security.csp.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $mode = (string) config('pqrs.security.csp');
        $nonce = $mode === 'off' ? null : Vite::useCspNonce();

        $response = $next($request);
        $headers = $response->headers;

        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('X-Frame-Options', 'DENY');
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(), usb=()');

        if ($request->isSecure()) {
            // Sin includeSubDomains: otros subdominios de la empresa podrían
            // no tener HTTPS.
            $headers->set('Strict-Transport-Security', 'max-age=31536000');
        }

        if ($nonce !== null) {
            $headers->set(
                $mode === 'enforce' ? 'Content-Security-Policy' : 'Content-Security-Policy-Report-Only',
                $this->policy($nonce, $request->isSecure()),
            );
        }

        return $response;
    }

    private function policy(string $nonce, bool $secure): string
    {
        [$dev, $devSocket] = $this->viteDevServer();
        $fonts = 'https://fonts.bunny.net';

        $directives = [
            'default-src' => "'self'",
            'script-src' => "'self' 'nonce-{$nonce}' {$dev}",
            // Estilos en línea: los usa React (colores de cada empresa) y no
            // ejecutan código.
            'style-src' => "'self' 'unsafe-inline' {$fonts} {$dev}",
            'font-src' => "'self' data: {$fonts} {$dev}",
            'img-src' => "'self' data: blob: {$dev}",
            'connect-src' => "'self' {$dev} {$devSocket}",
            // El «No soy un robot» resuelve su cálculo en workers creados al vuelo.
            'worker-src' => "'self' blob:",
            'frame-ancestors' => "'none'",
            'base-uri' => "'self'",
            'form-action' => "'self'",
            'object-src' => "'none'",
            'manifest-src' => "'self'",
        ];

        $policy = collect($directives)
            ->map(fn (string $value, string $name) => trim(preg_replace('/\s+/', ' ', "{$name} {$value}") ?? ''))
            ->implode('; ');

        return $secure ? $policy.'; upgrade-insecure-requests' : $policy;
    }

    /**
     * Origen del servidor de Vite cuando corre «npm run dev».
     *
     * @return array{0: string, 1: string}
     */
    private function viteDevServer(): array
    {
        if (! Vite::isRunningHot()) {
            return ['', ''];
        }

        $url = trim((string) File::get(Vite::hotFile()));
        $parts = parse_url($url);

        if (! isset($parts['scheme'], $parts['host'])) {
            return ['', ''];
        }

        $origin = $parts['scheme'].'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '');
        $socket = ($parts['scheme'] === 'https' ? 'wss' : 'ws').'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '');

        return [$origin, $socket];
    }
}
