<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transportista_liquidation_meta', function (Blueprint $table) {
            if (! Schema::hasColumn('transportista_liquidation_meta', 'tipo_periodo')) {
                $table->enum('tipo_periodo', ['quincenal', 'mensual'])->nullable()->after('plaza');
                $table->index('tipo_periodo');
            }
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE driver_import_runs MODIFY tipo_periodo ENUM('quincenal', 'mensual', 'mixto') NOT NULL");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE driver_import_runs MODIFY tipo_periodo ENUM('quincenal', 'mensual') NOT NULL");
        }

        Schema::table('transportista_liquidation_meta', function (Blueprint $table) {
            if (Schema::hasColumn('transportista_liquidation_meta', 'tipo_periodo')) {
                $table->dropIndex(['tipo_periodo']);
                $table->dropColumn('tipo_periodo');
            }
        });
    }
};
