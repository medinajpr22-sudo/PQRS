<?php

namespace App\Enums;

/**
 * Nivel de riesgo de un reclamo en salud (clasificación de la Supersalud).
 *
 * El ciudadano lo elige al radicar un reclamo, si la empresa lo habilita
 * en su formulario. Los de riesgo vital se atienden con prioridad.
 */
enum RiskLevel: string
{
    case Simple = 'simple';
    case Priorizado = 'priorizado';
    case Vital = 'vital';

    public function label(): string
    {
        return match ($this) {
            self::Simple => 'Riesgo simple',
            self::Priorizado => 'Riesgo priorizado',
            self::Vital => 'Riesgo vital',
        };
    }

    public function title(): string
    {
        return match ($this) {
            self::Simple => 'Sin riesgo inminente',
            self::Priorizado => 'Riesgo para la integridad',
            self::Vital => 'Riesgo inminente',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Simple => 'Insatisfacción con el servicio, sin riesgo inminente para la vida ni la integridad del usuario, sin afectar a población vulnerable y sin causar gran impacto en el sector.',
            self::Priorizado => 'Involucra algún riesgo para la integridad de las personas, afecta a poblaciones vulnerables o causa gran impacto en el Sistema General de Seguridad Social en Salud o en el sector salud.',
            self::Vital => 'Involucra un riesgo inminente para la vida o la integridad de la persona: una condición clínica de riesgo vital, o una condición que, sin el servicio requerido en el corto plazo, podría deteriorarse rápidamente.',
        };
    }

    /**
     * Orden de atención: primero el riesgo vital.
     */
    public function priority(): int
    {
        return match ($this) {
            self::Vital => 0,
            self::Priorizado => 1,
            self::Simple => 2,
        };
    }

    /**
     * @return list<array{value: string, label: string, title: string, description: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $r) => [
            'value' => $r->value,
            'label' => $r->label(),
            'title' => $r->title(),
            'description' => $r->description(),
        ], self::cases());
    }
}
