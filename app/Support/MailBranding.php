<?php

namespace App\Support;

use App\Models\Tenant;

/**
 * Marca de los correos: nombre, color y logo de la empresa que los envía.
 *
 * Los correos de una empresa se arman dentro de su contexto (también en la
 * cola), así que basta con mirar el tenant actual. Fuera de una empresa
 * (p. ej. recuperar la contraseña del super admin) se usa la marca central.
 */
final class MailBranding
{
    private const CENTRAL_COLOR = '#5b1a24';

    /**
     * Nombre del logo incrustado: el encabezado lo pide como «cid:logo-empresa»
     * y el listener EmbedCompanyLogo lo adjunta al correo al enviarlo.
     */
    public const LOGO_CID = 'logo-empresa';

    /**
     * Formatos que se ven en Gmail, Outlook y Apple Mail (webp no se ve en
     * Outlook de escritorio).
     *
     * @var array<string, string>
     */
    private const EMBEDDABLE = ['png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'gif' => 'image/gif'];

    /** Un logo más pesado viaja en cada correo: mejor enlazarlo. */
    private const MAX_EMBED_BYTES = 300 * 1024;

    /**
     * @return array{name: string, color: string, on_color: string, ink: string, soft: string, border: string, url: string, logo: string|null}
     */
    public static function current(): array
    {
        $tenant = tenancy()->initialized ? tenant() : null;

        if ($tenant instanceof Tenant) {
            $theme = $tenant->resolvedTheme();
            $url = $tenant->portalUrl() ?? (string) config('app.url');
            $color = self::validHex($theme['primary_color'] ?? null) ?? self::CENTRAL_COLOR;

            // El logo va dentro del correo: así se ve aunque el correo se abra
            // sin conexión, en Outlook (que bloquea las imágenes de internet)
            // o en desarrollo. Si no se puede incrustar, se enlaza.
            $logo = match (true) {
                self::embeddableLogo($tenant) !== null => 'cid:'.self::LOGO_CID,
                $theme['logo_url'] !== null && ! self::isLocal($url) => $url.$theme['logo_url'],
                default => null,
            };

            return self::build((string) $tenant->name, $color, $url, $logo);
        }

        return self::build((string) config('app.name'), self::CENTRAL_COLOR, (string) config('app.url'), null);
    }

    /**
     * Archivo del logo de la empresa, si se puede incrustar en el correo.
     *
     * Los logos los sube el super administrador al disco público central (el
     * mismo que sirve /storage/… en el portal). Dentro de una empresa ese
     * disco apunta a la carpeta de la empresa, así que se busca por el enlace
     * public/storage.
     *
     * @return array{path: string, mime: string}|null
     */
    public static function embeddableLogo(Tenant $tenant): ?array
    {
        $relative = $tenant->resolvedTheme()['logo_path'] ?? null;

        if (! is_string($relative) || $relative === '' || str_contains($relative, '..')) {
            return null;
        }

        $mime = self::EMBEDDABLE[strtolower(pathinfo($relative, PATHINFO_EXTENSION))] ?? null;
        $path = public_path('storage/'.$relative);

        if ($mime === null || ! is_file($path) || filesize($path) > self::MAX_EMBED_BYTES) {
            return null;
        }

        return ['path' => $path, 'mime' => $mime];
    }

    /**
     * @return array{name: string, color: string, on_color: string, ink: string, soft: string, border: string, url: string, logo: string|null}
     */
    private static function build(string $name, string $color, string $url, ?string $logo): array
    {
        return [
            'name' => $name,
            'color' => $color,
            'on_color' => self::readableOn($color),
            // Para textos sobre fondo claro: el color de la empresa si se lee
            // bien sobre blanco; si es muy claro (p. ej. amarillo), casi negro.
            'ink' => 1.05 / (self::luminance($color) + 0.05) >= 4.5 ? $color : '#18181b',
            'soft' => self::tint($color, 0.08),
            'border' => self::tint($color, 0.3),
            'url' => $url,
            'logo' => $logo,
        ];
    }

    private static function validHex(mixed $value): ?string
    {
        return is_string($value) && preg_match('/^#[0-9a-f]{6}$/i', $value) ? strtolower($value) : null;
    }

    /**
     * Blanco o casi negro, el que más contraste tenga con el color (WCAG).
     */
    private static function readableOn(string $hex): string
    {
        $luminance = self::luminance($hex);
        $withWhite = 1.05 / ($luminance + 0.05);
        $withDark = ($luminance + 0.05) / (self::luminance('#18181b') + 0.05);

        return $withWhite >= $withDark ? '#ffffff' : '#18181b';
    }

    private static function luminance(string $hex): float
    {
        [$r, $g, $b] = array_map(function (string $part): float {
            $c = hexdec($part) / 255;

            return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        }, str_split(substr($hex, 1), 2));

        return 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;
    }

    /**
     * Versión muy clara del color (mezclado con blanco) para fondos suaves.
     */
    private static function tint(string $hex, float $amount): string
    {
        $parts = array_map(
            fn (string $part) => (int) round(255 - (255 - hexdec($part)) * $amount),
            str_split(substr($hex, 1), 2),
        );

        return sprintf('#%02x%02x%02x', ...$parts);
    }

    private static function isLocal(string $url): bool
    {
        $host = (string) parse_url($url, PHP_URL_HOST);

        return $host === 'localhost' || $host === '127.0.0.1' || str_ends_with($host, '.localhost') || str_ends_with($host, '.test');
    }
}
