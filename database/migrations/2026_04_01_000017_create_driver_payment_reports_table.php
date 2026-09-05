<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('driver_payment_reports')) {
            Schema::create('driver_payment_reports', function (Blueprint $table) {
                $table->id();
                $table->string('nombre');
                $table->string('alias');
                $table->longText('sql_query');
                $table->timestamps();
            });
        }

        $exists = DB::table('driver_payment_reports')
            ->where('nombre', 'choferes_sin_cbu_cvu')
            ->exists();

        if (! $exists) {
            DB::table('driver_payment_reports')->insert([
                'nombre' => 'choferes_sin_cbu_cvu',
                'alias' => 'Choferes sin CBU/CVU',
                'sql_query' => "SELECT t.id, t.name AS chofer, t.dni, t.phone AS telefono, t.email\nFROM transportistas t\nLEFT JOIN transportista_payment_methods pm ON pm.transportista_id = t.id AND pm.is_default = 1\nWHERE TRIM(COALESCE(pm.cbu, '')) = ''\n  AND TRIM(COALESCE(t.cbu, '')) = ''\nORDER BY t.name",
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('driver_payment_reports');
    }
};
