<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Registro de las PQRS que eliminó un super administrador: cuál, de qué
     * empresa, por qué y quién. No guarda datos del ciudadano (eliminarlos
     * es justamente el punto), solo lo necesario para responder «¿qué pasó
     * con el radicado X?».
     *
     * Sin llave foránea a tenants: el registro sigue aunque la empresa se
     * elimine después.
     */
    public function up(): void
    {
        Schema::create('pqrs_deletions', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->index();
            $table->string('tenant_name');
            $table->string('radicado', 40);
            $table->string('type', 40);
            $table->string('status', 20);
            $table->timestamp('filed_at')->nullable();
            $table->string('reason', 20);
            $table->string('detail', 300)->nullable();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('deleted_by_name');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pqrs_deletions');
    }
};
