<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('traffic_zones', function (Blueprint $table) {
            if (! Schema::hasColumn('traffic_zones', 'uses_model_year_values')) {
                $table->boolean('uses_model_year_values')->default(false)->after('max_stops');
            }
        });

        if (! Schema::hasTable('driver_payment_zone_concept_year_values')) {
            Schema::create('driver_payment_zone_concept_year_values', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('traffic_zone_id');
                $table->unsignedBigInteger('driver_payment_concept_id');
                $table->string('vehicle_type');
                $table->unsignedSmallInteger('model_year');
                $table->decimal('amount', 14, 2);
                $table->timestamps();

                $table->unique(
                    ['traffic_zone_id', 'driver_payment_concept_id', 'vehicle_type', 'model_year'],
                    'dp_zc_year_vals_unique'
                );
                $table->foreign('traffic_zone_id', 'dp_zc_year_vals_zone_fk')
                    ->references('id')
                    ->on('traffic_zones')
                    ->cascadeOnDelete();
                $table->foreign('driver_payment_concept_id', 'dp_zc_year_vals_concept_fk')
                    ->references('id')
                    ->on('driver_payment_concepts')
                    ->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('driver_payment_zone_concept_year_values');

        Schema::table('traffic_zones', function (Blueprint $table) {
            if (Schema::hasColumn('traffic_zones', 'uses_model_year_values')) {
                $table->dropColumn('uses_model_year_values');
            }
        });
    }
};
