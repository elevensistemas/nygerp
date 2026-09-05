<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('driver_payment_concepts')) {
            Schema::create('driver_payment_concepts', function (Blueprint $table) {
                $table->id();
                $table->string('name')->unique();
                $table->boolean('active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('driver_payment_zone_concepts')) {
            Schema::create('driver_payment_zone_concepts', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('traffic_zone_id');
                $table->unsignedBigInteger('driver_payment_concept_id');
                $table->decimal('monto_efectivo', 14, 2);
                $table->boolean('active')->default(true);
                $table->timestamps();

                $table->foreign('traffic_zone_id')->references('id')->on('traffic_zones')->cascadeOnDelete();
                $table->foreign('driver_payment_concept_id')->references('id')->on('driver_payment_concepts')->cascadeOnDelete();
                $table->unique(['traffic_zone_id', 'driver_payment_concept_id'], 'driver_payment_zone_concept_unique');
                $table->index(['traffic_zone_id', 'active'], 'driver_payment_zone_active_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('driver_payment_zone_concepts');
        Schema::dropIfExists('driver_payment_concepts');
    }
};

