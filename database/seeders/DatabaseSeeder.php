<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Datos iniciales de la base de datos central: solo el super
     * administrador. Las empresas se crean desde el panel central.
    
     */
    public function run(): void
    {
        $email = config('PQRS_SUPER_ADMIN_EMAIL');
        $password = config('PQRS_SUPER_ADMIN_PASSWORD');

        if (blank($password)) {
            if (app()->isProduction()) {
                $this->command?->error('error para crear el super administrador.');

                return;
            }

          
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
