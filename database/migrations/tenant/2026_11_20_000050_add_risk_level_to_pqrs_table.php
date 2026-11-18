<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Nivel de riesgo de los reclamos en salud (simple, priorizado, vital).
     * Solo se llena en reclamos de empresas que lo habilitan en su formulario.
     */
    public function up(): void
    {
        Schema::table('pqrs', function (Blueprint $table) {
            $table->string('risk_level', 20)->nullable()->after('type')->index();
        });
    }

    public function down(): void
    {
        Schema::table('pqrs', function (Blueprint $table) {
            $table->dropIndex(['risk_level']);
            $table->dropColumn('risk_level');
        });
    }
};
