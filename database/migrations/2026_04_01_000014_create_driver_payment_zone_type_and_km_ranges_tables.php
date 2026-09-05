<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('driver_payment_zone_settings')) {
            Schema::create('driver_payment_zone_settings', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('traffic_zone_id');
                $table->enum('calc_type', ['ZONA', 'KM', 'PAQUETE'])->default('ZONA');
                $table->decimal('package_rate', 14, 4)->nullable();
                $table->timestamps();

                $table->foreign('traffic_zone_id')->references('id')->on('traffic_zones')->cascadeOnDelete();
                $table->unique('traffic_zone_id', 'driver_payment_zone_settings_zone_unique');
            });
        }

        if (! Schema::hasTable('driver_payment_km_ranges')) {
            Schema::create('driver_payment_km_ranges', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('traffic_zone_id')->nullable();
                $table->decimal('km_from', 14, 3);
                $table->decimal('km_to', 14, 3);
                $table->decimal('amount', 14, 2);
                $table->boolean('active')->default(true);
                $table->timestamps();

                $table->foreign('traffic_zone_id')->references('id')->on('traffic_zones')->cascadeOnDelete();
                $table->index(['traffic_zone_id', 'active'], 'driver_payment_km_ranges_scope_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('driver_payment_km_ranges');
        Schema::dropIfExists('driver_payment_zone_settings');
    }
};

