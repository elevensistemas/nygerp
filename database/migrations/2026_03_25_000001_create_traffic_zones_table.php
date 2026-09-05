<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('traffic_zones', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('type')->default('circle'); // circle|polygon
            $table->double('center_lat')->nullable();
            $table->double('center_lng')->nullable();
            $table->double('radius_km')->nullable(); // for circle
            $table->json('polygon')->nullable(); // for polygon (array of [lat,lng])
            $table->string('priority')->default('primary'); // primary|secondary
            $table->boolean('is_soft')->default(false); // soft means assignable with penalty
            $table->integer('max_stops')->nullable();
            $table->timestamps();
        });

        Schema::create('traffic_zone_transportista', function (Blueprint $table) {
            $table->id();
            $table->foreignId('traffic_zone_id')->constrained('traffic_zones')->cascadeOnDelete();
            $table->foreignId('transportista_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['traffic_zone_id', 'transportista_id'], 'tz_transp_uniq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('traffic_zone_transportista');
        Schema::dropIfExists('traffic_zones');
    }
};
