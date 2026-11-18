<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Contador de radicados por prefijo y año (p. ej. «AND-2026» => 37).
     *
     * Reemplaza el cálculo "último radicado + 1", que ordenaba como texto y
     * podía repetir números.
     */
    public function up(): void
    {
        Schema::create('pqrs_sequences', function (Blueprint $table) {
            $table->string('scope', 60)->primary();
            $table->unsignedInteger('last_value')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pqrs_sequences');
    }
};
