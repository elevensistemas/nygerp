<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('driver_liquidation_settings')) {
            return;
        }

        Schema::table('driver_liquidation_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('driver_liquidation_settings', 'desde_dia')) {
                $table->unsignedTinyInteger('desde_dia')->nullable()->after('quincena');
            }
            if (! Schema::hasColumn('driver_liquidation_settings', 'hasta_dia')) {
                $table->unsignedTinyInteger('hasta_dia')->nullable()->after('desde_dia');
            }
        });

        $driver = Schema::getConnection()->getDriverName();
        if (Schema::hasColumn('driver_liquidation_settings', 'desde') && Schema::hasColumn('driver_liquidation_settings', 'hasta')) {
            if ($driver === 'sqlite') {
                DB::statement("UPDATE driver_liquidation_settings SET desde_dia = CAST(strftime('%d', desde) AS INTEGER), hasta_dia = CAST(strftime('%d', hasta) AS INTEGER)");
            } else {
                DB::statement('UPDATE driver_liquidation_settings SET desde_dia = DAYOFMONTH(`desde`), hasta_dia = DAYOFMONTH(`hasta`)');
            }
        }

        Schema::table('driver_liquidation_settings', function (Blueprint $table) {
            try {
                $table->dropIndex('driver_liquidation_settings_lookup_idx');
            } catch (\Throwable $e) {
                // ignore if index is missing
            }
        });

        Schema::table('driver_liquidation_settings', function (Blueprint $table) {
            $dropColumns = [];
            foreach (['anio', 'mes', 'desde', 'hasta'] as $column) {
                if (Schema::hasColumn('driver_liquidation_settings', $column)) {
                    $dropColumns[] = $column;
                }
            }

            if (! empty($dropColumns)) {
                $table->dropColumn($dropColumns);
            }
        });

        Schema::table('driver_liquidation_settings', function (Blueprint $table) {
            if (Schema::hasColumn('driver_liquidation_settings', 'desde_dia')) {
                $table->renameColumn('desde_dia', 'desde');
            }
            if (Schema::hasColumn('driver_liquidation_settings', 'hasta_dia')) {
                $table->renameColumn('hasta_dia', 'hasta');
            }
        });

        Schema::table('driver_liquidation_settings', function (Blueprint $table) {
            $table->index(['tipo_periodo', 'quincena', 'activo'], 'driver_liquidation_settings_lookup_idx');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('driver_liquidation_settings')) {
            return;
        }

        Schema::table('driver_liquidation_settings', function (Blueprint $table) {
            try {
                $table->dropIndex('driver_liquidation_settings_lookup_idx');
            } catch (\Throwable $e) {
                // ignore if index is missing
            }

            if (! Schema::hasColumn('driver_liquidation_settings', 'anio')) {
                $table->unsignedSmallInteger('anio')->nullable()->after('tipo_periodo');
            }
            if (! Schema::hasColumn('driver_liquidation_settings', 'mes')) {
                $table->unsignedTinyInteger('mes')->nullable()->after('anio');
            }
            if (! Schema::hasColumn('driver_liquidation_settings', 'desde_fecha')) {
                $table->date('desde_fecha')->nullable()->after('quincena');
            }
            if (! Schema::hasColumn('driver_liquidation_settings', 'hasta_fecha')) {
                $table->date('hasta_fecha')->nullable()->after('desde_fecha');
            }
        });

        $driver = Schema::getConnection()->getDriverName();
        if (Schema::hasColumn('driver_liquidation_settings', 'desde') && Schema::hasColumn('driver_liquidation_settings', 'hasta')) {
            if ($driver === 'sqlite') {
                DB::statement("UPDATE driver_liquidation_settings SET desde_fecha = printf('2000-01-%02d', desde), hasta_fecha = printf('2000-01-%02d', hasta)");
            } else {
                DB::statement("UPDATE driver_liquidation_settings SET desde_fecha = STR_TO_DATE(CONCAT('2000-01-', LPAD(`desde`, 2, '0')), '%Y-%m-%d'), hasta_fecha = STR_TO_DATE(CONCAT('2000-01-', LPAD(`hasta`, 2, '0')), '%Y-%m-%d')");
            }
        }

        Schema::table('driver_liquidation_settings', function (Blueprint $table) {
            $dropColumns = [];
            foreach (['desde', 'hasta'] as $column) {
                if (Schema::hasColumn('driver_liquidation_settings', $column)) {
                    $dropColumns[] = $column;
                }
            }

            if (! empty($dropColumns)) {
                $table->dropColumn($dropColumns);
            }
        });

        Schema::table('driver_liquidation_settings', function (Blueprint $table) {
            if (Schema::hasColumn('driver_liquidation_settings', 'desde_fecha')) {
                $table->renameColumn('desde_fecha', 'desde');
            }
            if (Schema::hasColumn('driver_liquidation_settings', 'hasta_fecha')) {
                $table->renameColumn('hasta_fecha', 'hasta');
            }
            $table->index(['tipo_periodo', 'anio', 'mes', 'quincena', 'activo'], 'driver_liquidation_settings_lookup_idx');
        });
    }
};
