<?php

namespace App\Services;

use AltchaOrg\Altcha\Algorithm\Pbkdf2;
use AltchaOrg\Altcha\Altcha;
use AltchaOrg\Altcha\CreateChallengeOptions;
use AltchaOrg\Altcha\Payload;
use AltchaOrg\Altcha\VerifySolutionOptions;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;

/**
 * «No soy un robot» del portal, con ALTCHA (prueba de trabajo).
 *
 * El servidor entrega un reto firmado; el navegador lo resuelve probando
 * contraseñas hasta dar con la clave y envía la solución con el formulario.
 * Cada reto queda atado a la empresa, vence a los pocos minutos y sirve una
 * sola vez, así que no se puede reutilizar para enviar PQRS en masa.
 */
class Captcha
{
    public function enabled(): bool
    {
        return (bool) config('pqrs.captcha.enabled');
    }

    /**
     * Un reto nuevo para el widget, atado a la empresa actual.
     *
     * @return array<string, mixed>
     */
    public function challenge(): array
    {
        $counter = config('pqrs.captcha.counter');

        return $this->altcha()->createChallenge(new CreateChallengeOptions(
            algorithm: new Pbkdf2,
            cost: max(1, (int) config('pqrs.captcha.cost')),
            counter: random_int((int) $counter['min'], max((int) $counter['min'], (int) $counter['max'])),
            expiresAt: now()->addMinutes((int) config('pqrs.captcha.expires_minutes'))->getTimestamp(),
            data: ['tenant' => (string) tenant('id')],
        ))->toArray();
    }

    /**
     * ¿La solución es válida, no venció y es de esta empresa? No la gasta:
     * si el resto del formulario tiene errores, el ciudadano corrige y la
     * vuelve a enviar sin marcar la casilla otra vez.
     */
    public function verify(mixed $payload): bool
    {
        $parsed = $this->parse($payload);

        // La librería revisa el vencimiento con el reloj del sistema; aquí
        // también con el de la aplicación, para que las pruebas lo controlen.
        if ($parsed === null || (int) $parsed->challenge->parameters->expiresAt < now()->getTimestamp()) {
            return false;
        }

        $result = $this->altcha()->verifySolution(new VerifySolutionOptions(
            payload: $parsed,
            algorithm: new Pbkdf2,
        ));

        return $result->verified
            && ($parsed->challenge->parameters->data['tenant'] ?? null) === (string) tenant('id');
    }

    /**
     * Marca la solución como usada. Devuelve false si ya se había usado.
     */
    public function consume(mixed $payload): bool
    {
        $parsed = $this->parse($payload);

        if ($parsed === null) {
            return false;
        }

        // La firma es única por reto. Se guarda hasta que el reto vence:
        // después de eso ya no pasaría la verificación de todas formas.
        $expiresAt = (int) ($parsed->challenge->parameters->expiresAt ?? 0);
        $ttl = max(60, $expiresAt - now()->getTimestamp() + 60);

        return Cache::add('captcha-used:'.hash('sha256', (string) $parsed->challenge->signature), true, $ttl);
    }

    private function parse(mixed $payload): ?Payload
    {
        if (! is_string($payload) || $payload === '' || strlen($payload) > 8000) {
            return null;
        }

        try {
            return Payload::fromBase64($payload);
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    private function altcha(): Altcha
    {
        // Las claves salen de APP_KEY: no hay que configurar nada más.
        $appKey = (string) config('app.key');

        return new Altcha(
            hmacSignatureSecret: hash_hmac('sha256', 'altcha-signature', $appKey),
            hmacKeySignatureSecret: hash_hmac('sha256', 'altcha-key-signature', $appKey),
        );
    }
}
