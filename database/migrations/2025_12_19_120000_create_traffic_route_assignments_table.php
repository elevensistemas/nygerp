<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('traffic_route_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('traffic_route_id')->constrained('traffic_routes')->cascadeOnDelete();
            $table->foreignId('transportista_id')->constrained('transportistas');
            $table->foreignId('transporte_id')->nullable()->constrained('transportes');
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('completed_stops')->default(0);
            $table->timestamp('assigned_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('traffic_route_assignments');
    }
};
