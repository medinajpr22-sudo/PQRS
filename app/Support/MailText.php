<?php

namespace App\Support;

/**
 * Texto escrito por el ciudadano (nombre, asunto) para ponerlo en un correo.
 *
 * Los correos salen del buzón de la empresa, con su logo: si alguien radica
 * con un nombre como «Juan. Su afiliación fue suspendida, actualice en
 * eps-pagos.co» y el correo de otra persona, Gmail y Outlook convertirían
 * «eps-pagos.co» en un enlace. Un espacio invisible después de cada punto
 * (o «://» y «@») entre letras evita que se vuelva enlace; el texto se lee
 * igual.
 */
final class MailText
{
    private const INVISIBLE = "\u{200B}";

    public static function inert(?string $text): string
    {
        $text = (string) $text;

        return (string) preg_replace(
            ['/(?<=[\p{L}\p{N}])\.(?=[\p{L}\p{N}])/u', '/:\/\//u', '/(?<=[\p{L}\p{N}])@(?=[\p{L}\p{N}])/u'],
            ['.'.self::INVISIBLE, ':'.self::INVISIBLE.'//', '@'.self::INVISIBLE],
            $text,
        );
    }
}
