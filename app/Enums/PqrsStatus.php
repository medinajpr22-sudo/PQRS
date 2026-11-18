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
     * Estados a los que un funcionario puede pasar la solicitud a mano.
     *
     * «Respondida» no está aquí a propósito: solo se llega enviando una
     * respuesta, para que siempre quede la fecha y se notifique al ciudadano.
     *
     * @return list<self>
     */
    public function manualTransitions(): array
    {
        return match ($this) {
            self::Radicada => [self::EnTramite, self::Cerrada],
            self::EnTramite => [self::Cerrada],
            self::Respondida => [self::Cerrada, self::EnTramite],
            self::Cerrada => [self::EnTramite],
        };
    }

    public function canTransitionTo(self $to): bool
    {
        return in_array($to, $this->manualTransitions(), true);
    }

    /**
     * Reabrir una solicitud o cerrarla sin haberla respondido exige un motivo
     * que queda en el historial.
     */
    public function requiresReasonTo(self $to): bool
    {
        return ($to === self::Cerrada && $this->isOpen())
            || ($to->isOpen() && ! $this->isOpen());
    }

    /**
     * Opciones para la pantalla de detalle: estado actual + cambios permitidos.
     *
     * @return list<array{value: string, label: string, requires_reason: bool}>
     */
    public function transitionOptions(): array
    {
        return array_map(fn (self $s) => [
            'value' => $s->value,
            'label' => $s->label(),
            'requires_reason' => $this->requiresReasonTo($s),
        ], $this->manualTransitions());
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
