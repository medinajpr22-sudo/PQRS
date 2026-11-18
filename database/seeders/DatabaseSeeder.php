<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Datos iniciales de la base de datos central.
     *
     * Credenciales de prueba (solo desarrollo):
     *   Panel central:  admin@pqrs.test / Admin123*
     *   Cada empresa:   admin@<id>.test  / Admin123*  (administrador de empresa, ve todas las sedes)
     *                   agente@<id>.test / Admin123*  (agente, solo la primera sede; ver DemoTenantsSeeder)
     */
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'admin@pqrs.test'],
            [
                'name' => 'Super Administrador',
                'password' => 'Admin123*',
                'email_verified_at' => now(),
            ],
        );

        $this->call(DemoTenantsSeeder::class);
    }
}
