<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Str;

/**
 * El dominio del correo existe y recibe correos (tiene registro MX; una
 * página web sola no basta). Así, «ana@gmail.co» se corrige en el
 * formulario en vez de rebotar después: cada rebote le resta reputación al
 * buzón que envía.
 *
 * Si el DNS del servidor no responde (ni siquiera para gmail.com), deja
 * pasar el correo: es preferible un rebote a impedir que se radique.
 */
final class EmailDomainReceivesMail implements ValidationRule
{
    /** Dominio que siempre tiene MX, para saber si el DNS del servidor funciona. */
    private const CANARY = 'gmail.com';

    /** @var array<string, bool> Dominios ya consultados en esta validación. */
    private array $cache = [];

    /** @var (Closure(string): bool)|null Consulta de reemplazo (pruebas). */
    private static ?Closure $resolver = null;

    /**
     * Reemplaza la consulta al DNS (para las pruebas); null la restablece.
     *
     * @param  (Closure(string): bool)|null  $resolver
     */
    public static function resolveUsing(?Closure $resolver): void
    {
        self::$resolver = $resolver;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! str_contains($value, '@')) {
            return;
        }

        $domain = self::ascii(mb_strtolower(trim(Str::afterLast($value, '@'))));

        if ($domain === null || $this->receivesMail($domain) || ! $this->receivesMail(self::CANARY)) {
            return;
        }

        $fail('Revisa el :attribute: el dominio «'.Str::afterLast(trim($value), '@').'» no existe o no recibe correos.');
    }

    private function receivesMail(string $domain): bool
    {
        if (self::$resolver !== null) {
            return $this->cache[$domain] ??= (self::$resolver)($domain);
        }

        return $this->cache[$domain] ??= self::hasMailServer($domain);
    }

    /**
     * Tiene al menos un servidor de correo. Un MX vacío («MX nulo», como el
     * de gmail.co) es el dueño del dominio diciendo que ahí no recibe.
     */
    private static function hasMailServer(string $domain): bool
    {
        // El punto final evita que el servidor le pegue su propio dominio.
        $records = @dns_get_record($domain.'.', DNS_MX);

        if (! is_array($records)) {
            return false;
        }

        foreach ($records as $record) {
            if (trim((string) ($record['target'] ?? ''), '. ') !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * Dominios con tildes o eñes («españa.co») en la forma que entiende el DNS.
     */
    private static function ascii(string $domain): ?string
    {
        if ($domain === '' || preg_match('/^[\x20-\x7e]+$/', $domain) === 1) {
            return $domain === '' ? null : $domain;
        }

        if (! function_exists('idn_to_ascii')) {
            return null;
        }

        $ascii = idn_to_ascii($domain, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);

        return $ascii === false ? null : $ascii;
    }
}
