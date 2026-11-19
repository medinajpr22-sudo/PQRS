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
     * @return array{name: string, color: string, on_color: string, ink: string, soft: string, border: string, url: string, logo: string|null}
     */
    public static function current(): array
    {
        $tenant = tenancy()->initialized ? tenant() : null;

        if ($tenant instanceof Tenant) {
            $theme = $tenant->resolvedTheme();
            $url = $tenant->portalUrl() ?? (string) config('app.url');
            $color = self::validHex($theme['primary_color'] ?? null) ?? self::CENTRAL_COLOR;

            return self::build((string) $tenant->name, $color, $url, $theme['logo_url'] ? $url.$theme['logo_url'] : null);
        }

        return self::build((string) config('app.name'), self::CENTRAL_COLOR, (string) config('app.url'), null);
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
            // Un logo en localhost no se ve en Gmail ni Outlook (no pueden
            // descargarlo): en desarrollo se muestra el nombre.
            'logo' => $logo !== null && ! self::isLocal($url) ? $logo : null,
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
