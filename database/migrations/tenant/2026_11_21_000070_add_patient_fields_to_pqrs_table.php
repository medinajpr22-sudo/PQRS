<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Datos de salud del formulario (empresas IPS/EPS):
     *
     *   - Quién recibió el servicio: el mismo solicitante o otra persona
     *     (paciente afectado), con su identificación y contacto, y el
     *     parentesco de quien radica.
     *   - EPS y régimen del paciente, servicio recibido y fecha de atención.
     *
     * requester_is_affected es nulo cuando la empresa no pide estos datos.
     */
    public function up(): void
    {
        Schema::table('pqrs', function (Blueprint $table) {
            $table->boolean('requester_is_affected')->nullable();
            $table->string('requester_relationship', 60)->nullable();
            $table->string('affected_name', 150)->nullable();
            $table->string('affected_document_type', 10)->nullable();
            $table->string('affected_document', 30)->nullable();
            $table->string('affected_phone', 30)->nullable();
            $table->string('affected_email', 150)->nullable();
            $table->string('eps', 120)->nullable();
            $table->string('health_regime', 30)->nullable();
            $table->string('service', 120)->nullable()->index();
            $table->date('attended_on')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('pqrs', function (Blueprint $table) {
            $table->dropIndex(['service']);
            $table->dropColumn([
                'requester_is_affected',
                'requester_relationship',
                'affected_name',
                'affected_document_type',
                'affected_document',
                'affected_phone',
                'affected_email',
                'eps',
                'health_regime',
                'service',
                'attended_on',
            ]);
        });
    }
};
