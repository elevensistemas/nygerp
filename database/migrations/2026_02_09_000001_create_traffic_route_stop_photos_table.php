<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('traffic_route_stop_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('traffic_route_stop_id')
                ->constrained('traffic_route_stops')
                ->cascadeOnDelete();
            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->string('path');
            $table->string('filename')->nullable();
            $table->string('mimetype')->nullable();
            $table->unsignedInteger('size')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('traffic_route_stop_photos');
    }
};
