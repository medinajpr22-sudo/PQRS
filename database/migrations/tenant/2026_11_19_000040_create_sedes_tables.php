<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sedes de la empresa (p. ej. Neiva, Yopal, Bogotá).
     *
     *   - Cada PQRS pertenece a una sede (la elige el ciudadano al radicar).
     *   - Cada agente ve solo las PQRS de las sedes que tiene asignadas
     *     (tabla sede_user). Los administradores ven todas.
     *   - Las sedes se desactivan en lugar de borrarse, para no perder el
     *     historial de sus PQRS.
     *
     * Además el rol «funcionario» pasa a llamarse «agente».
     */
    public function up(): void
    {
        Schema::create('sedes', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120)->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('sede_user', function (Blueprint $table) {
            $table->foreignId('sede_id')->constrained('sedes')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->primary(['sede_id', 'user_id']);
        });

        Schema::table('pqrs', function (Blueprint $table) {
            // Nula en las PQRS radicadas antes de existir las sedes: solo los
            // administradores las ven hasta que les asignen una sede.
            $table->foreignId('sede_id')->nullable()->after('type')->constrained('sedes')->restrictOnDelete();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 20)->default('agente')->change();
        });

        DB::table('users')->where('role', 'funcionario')->update(['role' => 'agente']);
    }

    public function down(): void
    {
        DB::table('users')->where('role', 'agente')->update(['role' => 'funcionario']);

        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 20)->default('funcionario')->change();
        });

        Schema::table('pqrs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sede_id');
        });

        Schema::dropIfExists('sede_user');
        Schema::dropIfExists('sedes');
    }
};
