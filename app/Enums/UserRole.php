<?php

namespace App\Enums;

/**
 * Rol de un usuario dentro de su empresa (BD del tenant).
 *
 * El super administrador vive en la base central y no tiene rol de empresa.
 */
enum UserRole: string
{
    case Admin = 'admin';
    case Agente = 'agente';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrador de empresa',
            self::Agente => 'Agente',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Admin => 'Gestiona sedes, usuarios y todas las PQRS y reportes de la empresa.',
            self::Agente => 'Atiende solo las PQRS de las sedes que tiene asignadas.',
        };
    }

    /**
     * @return list<array{value: string, label: string, description: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $r) => [
            'value' => $r->value,
            'label' => $r->label(),
            'description' => $r->description(),
        ], self::cases());
    }
}
