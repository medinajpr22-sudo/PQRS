<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Columnas propias de cada empresa (tenant) en la base de datos central.
     */
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('name')->after('id');
            $table->string('nit', 30)->nullable()->after('name');
            $table->string('email')->nullable()->after('nit');
            $table->string('phone', 30)->nullable()->after('email');
            $table->string('status', 20)->default('active')->index()->after('phone');
            $table->string('plan', 20)->default('basico')->after('status');
            $table->string('radicado_prefix', 10)->after('plan');
            $table->json('theme')->nullable()->after('radicado_prefix');
            $table->json('form_schema')->nullable()->after('theme');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['name', 'nit', 'email', 'phone', 'status', 'plan', 'radicado_prefix', 'theme', 'form_schema']);
        });
    }
};
