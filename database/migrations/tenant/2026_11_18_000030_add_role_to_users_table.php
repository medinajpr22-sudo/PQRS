<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Roles de los funcionarios de la empresa:
     *   - admin:       gestiona usuarios y asigna PQRS a cualquiera.
     *   - funcionario: atiende PQRS; solo puede tomar las libres o soltar las suyas.
     *
     * Los usuarios se desactivan en lugar de borrarse, para no perder el historial.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 20)->default('funcionario')->after('email');
            $table->boolean('is_active')->default(true)->after('role');
        });

        // Hasta ahora cada empresa solo tenía su administrador inicial.
        DB::table('users')->update(['role' => 'admin']);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role', 'is_active']);
        });
    }
};
