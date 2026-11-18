<?php

namespace App\Enums;

/**
 * Rol de un funcionario dentro de su empresa (BD del tenant).
 */
enum UserRole: string
{
    case Admin = 'admin';
    case Funcionario = 'funcionario';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrador',
            self::Funcionario => 'Funcionario',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Admin => 'Gestiona usuarios y asigna solicitudes a cualquier funcionario.',
            self::Funcionario => 'Atiende solicitudes; puede tomar las que no tienen responsable.',
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
