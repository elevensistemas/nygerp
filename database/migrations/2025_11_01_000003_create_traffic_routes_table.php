<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTrafficRoutesTable extends Migration
{
    public function up()
    {
        Schema::create('traffic_routes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transportista_id')->constrained('transportistas')->cascadeOnDelete();
            $table->foreignId('transporte_id')->constrained('transportes')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('code')->unique();
            $table->string('status')->default('planned');
            $table->date('scheduled_date')->nullable();
            $table->integer('distance_meters')->nullable();
            $table->integer('duration_seconds')->nullable();
            $table->string('traffic_summary')->nullable();
            $table->string('congestion_level')->nullable();
            $table->json('geometry')->nullable();
            $table->json('traffic_report')->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('traffic_routes');
    }
}

