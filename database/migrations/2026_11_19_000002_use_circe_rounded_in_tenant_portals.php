<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Circe Rounded pasa a ser la tipografía institucional de todos los
     * portales. Las empresas existentes se cambian a ella (cada una puede
     * elegir otra después en «Diseño del portal»).
     */
    public function up(): void
    {
        foreach (DB::table('tenants')->get(['id', 'theme']) as $tenant) {
            $theme = json_decode((string) $tenant->theme, true) ?: [];
            $theme['font'] = 'circe-rounded';

            DB::table('tenants')->where('id', $tenant->id)->update(['theme' => json_encode($theme)]);
        }
    }

    public function down(): void
    {
        // Sin vuelta atrás: no se guardó la tipografía anterior de cada empresa.
    }
};
