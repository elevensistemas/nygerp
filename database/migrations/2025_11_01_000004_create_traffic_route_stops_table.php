<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTrafficRouteStopsTable extends Migration
{
    public function up()
    {
        Schema::create('traffic_route_stops', function (Blueprint $table) {
            $table->id();
            $table->foreignId('traffic_route_id')->constrained('traffic_routes')->cascadeOnDelete();
            $table->unsignedInteger('sequence')->default(0);
            $table->string('label')->nullable();
            $table->string('address');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('city')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('contact_name')->nullable();
            $table->string('contact_phone')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('traffic_route_stops');
    }
}

