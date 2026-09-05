<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('driver_payment_zone_concept_year_values')) {
            return;
        }

        Schema::table('driver_payment_zone_concept_year_values', function (Blueprint $table) {
            if (! Schema::hasColumn('driver_payment_zone_concept_year_values', 'year_from')) {
                $table->unsignedSmallInteger('year_from')->nullable()->after('vehicle_type');
            }

            if (! Schema::hasColumn('driver_payment_zone_concept_year_values', 'year_to')) {
                $table->unsignedSmallInteger('year_to')->nullable()->after('year_from');
            }
        });

        if (Schema::hasColumn('driver_payment_zone_concept_year_values', 'model_year')) {
            DB::table('driver_payment_zone_concept_year_values')
                ->whereNull('year_from')
                ->update([
                    'year_from' => DB::raw('model_year'),
                    'year_to' => DB::raw('model_year'),
                ]);
        }

        // Create the new index FIRST so that the existing foreign key on traffic_zone_id 
        // can use it when we drop the unique index.
        Schema::table('driver_payment_zone_concept_year_values', function (Blueprint $table) {
            // Check if index exists by querying schema first to avoid duplicate index errors
            $idx = DB::select("SHOW INDEXES FROM driver_payment_zone_concept_year_values WHERE Key_name = 'dp_zc_year_vals_lookup_idx'");
            if (empty($idx)) {
                $table->index(
                    ['traffic_zone_id', 'driver_payment_concept_id', 'vehicle_type', 'year_from', 'year_to'],
                    'dp_zc_year_vals_lookup_idx'
                );
            }
        });

        // Find any existing indexes that might include 'model_year' (like the unique ones) and drop them safely
        $indexes = DB::select("SHOW INDEXES FROM driver_payment_zone_concept_year_values WHERE Column_name = 'model_year'");
        foreach ($indexes as $index) {
            if ($index->Key_name !== 'PRIMARY') {
                try {
                    $keyName = $index->Key_name;
                    DB::statement("ALTER TABLE driver_payment_zone_concept_year_values DROP INDEX `{$keyName}`");
                } catch (\Throwable $e) {}
            }
        }

        Schema::table('driver_payment_zone_concept_year_values', function (Blueprint $table) {
            if (Schema::hasColumn('driver_payment_zone_concept_year_values', 'model_year')) {
                $table->dropColumn('model_year');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('driver_payment_zone_concept_year_values')) {
            return;
        }

        Schema::table('driver_payment_zone_concept_year_values', function (Blueprint $table) {
            if (! Schema::hasColumn('driver_payment_zone_concept_year_values', 'model_year')) {
                $table->unsignedSmallInteger('model_year')->nullable()->after('vehicle_type');
            }
        });

        DB::table('driver_payment_zone_concept_year_values')->update([
            'model_year' => DB::raw('coalesce(year_from, year_to)'),
        ]);

        try {
            Schema::table('driver_payment_zone_concept_year_values', function (Blueprint $table) {
                $table->dropIndex('dp_zc_year_vals_lookup_idx');
            });
        } catch (\Throwable $e) {
            // El indice puede no existir segun el estado de la base.
        }

        Schema::table('driver_payment_zone_concept_year_values', function (Blueprint $table) {
            if (Schema::hasColumn('driver_payment_zone_concept_year_values', 'year_to')) {
                $table->dropColumn('year_to');
            }

            if (Schema::hasColumn('driver_payment_zone_concept_year_values', 'year_from')) {
                $table->dropColumn('year_from');
            }
        });

        Schema::table('driver_payment_zone_concept_year_values', function (Blueprint $table) {
            $table->unique(
                ['traffic_zone_id', 'driver_payment_concept_id', 'vehicle_type', 'model_year'],
                'dp_zc_year_vals_unique'
            );
        });
    }
};
