<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Datos iniciales de la base de datos central: solo el super
     * administrador. Las empresas se crean desde el panel central.
     *
     * En desarrollo: admin@pqrs.test / Admin123*
     * En producción la contraseña conocida no sirve: hay que definir
     * PQRS_SUPER_ADMIN_EMAIL y PQRS_SUPER_ADMIN_PASSWORD en el .env.
     */
    public function run(): void
    {
        $email = config('pqrs.super_admin.email');
        $password = config('pqrs.super_admin.password');

        if (blank($password)) {
            if (app()->isProduction()) {
                $this->command?->error('Define PQRS_SUPER_ADMIN_PASSWORD en el .env para crear el super administrador.');

                return;
            }

            $password = 'Admin123*';
        }

        User::firstOrCreate(
            ['email' => $email],
            [
                'name' => 'Super Administrador',
                'password' => $password,
                'email_verified_at' => now(),
            ],
        );
    }
}
