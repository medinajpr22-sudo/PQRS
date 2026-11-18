<?php

namespace App\Enums;

/**
 * Tipos de solicitud soportados. Cada empresa decide cuáles habilita,
 * cómo se llaman en su portal y su tiempo de respuesta (días hábiles).
 */
enum PqrsType: string
{
    case Peticion = 'peticion';
    case Queja = 'queja';
    case Reclamo = 'reclamo';
    case Sugerencia = 'sugerencia';
    case Felicitacion = 'felicitacion';
    case Denuncia = 'denuncia';

    public function label(): string
    {
        return match ($this) {
            self::Peticion => 'Petición',
            self::Queja => 'Queja',
            self::Reclamo => 'Reclamo',
            self::Sugerencia => 'Sugerencia',
            self::Felicitacion => 'Felicitación',
            self::Denuncia => 'Denuncia',
        };
    }

    /**
     * Definición que ve el ciudadano en el portal (cada empresa puede
     * cambiarla en su formulario). Las empresas de salud suelen decir
     * «…con la prestación del servicio de salud» en el reclamo.
     */
    public function description(): string
    {
        return match ($this) {
            self::Peticion => 'Mediante la cual una persona, por motivos de interés general o particular, solicita la intervención de la entidad para la resolución de una situación, la prestación de un servicio, la información o el requerimiento de copia de documentos, entre otros.',
            self::Queja => 'Manifestación de una persona, a través de la cual expresa inconformidad con el actuar de un funcionario de la entidad.',
            self::Reclamo => 'Los usuarios dan a conocer su insatisfacción con la prestación de un servicio o la entrega de un producto de la entidad.',
            self::Sugerencia => 'Recomendación o propuesta que se formula para el mejoramiento de la prestación de los servicios a cargo de la entidad.',
            self::Felicitacion => 'Opiniones o palabras que enaltecen una labor realizada, un servicio o una persona en particular.',
            self::Denuncia => 'Reporte de conductas irregulares o posibles actos de corrupción.',
        };
    }

    /**
     * Plazo por defecto en días hábiles (Ley 1755 de 2015).
     */
    public function defaultSlaDays(): int
    {
        return 15;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $t) => $t->value, self::cases());
    }
}
