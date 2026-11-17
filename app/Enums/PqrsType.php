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

    public function description(): string
    {
        return match ($this) {
            self::Peticion => 'Solicitud de información, documentos o actuaciones.',
            self::Queja => 'Inconformidad con la atención o conducta de un colaborador.',
            self::Reclamo => 'Inconformidad por la prestación deficiente de un servicio o producto.',
            self::Sugerencia => 'Propuesta para mejorar nuestros servicios.',
            self::Felicitacion => 'Reconocimiento por un buen servicio recibido.',
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
