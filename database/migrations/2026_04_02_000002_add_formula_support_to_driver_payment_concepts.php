<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('driver_payment_concepts')) {
            Schema::table('driver_payment_concepts', function (Blueprint $table) {
                if (! Schema::hasColumn('driver_payment_concepts', 'value_type')) {
                    $table->string('value_type', 30)->default('fixed')->after('name');
                }
                if (! Schema::hasColumn('driver_payment_concepts', 'reference_concept_id')) {
                    $table->unsignedBigInteger('reference_concept_id')->nullable()->after('default_amount');
                }
                if (! Schema::hasColumn('driver_payment_concepts', 'reference_multiplier')) {
                    $table->decimal('reference_multiplier', 14, 4)->nullable()->after('reference_concept_id');
                }
            });
        }

        if (Schema::hasTable('driver_payment_zone_concepts')) {
            Schema::table('driver_payment_zone_concepts', function (Blueprint $table) {
                if (! Schema::hasColumn('driver_payment_zone_concepts', 'value_type')) {
                    $table->string('value_type', 30)->default('fixed')->after('vehicle_type');
                }
                if (! Schema::hasColumn('driver_payment_zone_concepts', 'reference_concept_id')) {
                    $table->unsignedBigInteger('reference_concept_id')->nullable()->after('monto_efectivo');
                }
                if (! Schema::hasColumn('driver_payment_zone_concepts', 'reference_multiplier')) {
                    $table->decimal('reference_multiplier', 14, 4)->nullable()->after('reference_concept_id');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('driver_payment_zone_concepts')) {
            Schema::table('driver_payment_zone_concepts', function (Blueprint $table) {
                foreach (['reference_multiplier', 'reference_concept_id', 'value_type'] as $column) {
                    if (Schema::hasColumn('driver_payment_zone_concepts', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('driver_payment_concepts')) {
            Schema::table('driver_payment_concepts', function (Blueprint $table) {
                foreach (['reference_multiplier', 'reference_concept_id', 'value_type'] as $column) {
                    if (Schema::hasColumn('driver_payment_concepts', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
