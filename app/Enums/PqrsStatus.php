<?php

namespace App\Enums;

enum PqrsStatus: string
{
    case Radicada = 'radicada';
    case EnTramite = 'en_tramite';
    case Respondida = 'respondida';
    case Cerrada = 'cerrada';

    public function label(): string
    {
        return match ($this) {
            self::Radicada => 'Radicada',
            self::EnTramite => 'En trámite',
            self::Respondida => 'Respondida',
            self::Cerrada => 'Cerrada',
        };
    }

    public function isOpen(): bool
    {
        return in_array($this, [self::Radicada, self::EnTramite], true);
    }

    /**
     * @return list<string>
     */
    public static function openValues(): array
    {
        return [self::Radicada->value, self::EnTramite->value];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $s) => ['value' => $s->value, 'label' => $s->label()], self::cases());
    }
}
