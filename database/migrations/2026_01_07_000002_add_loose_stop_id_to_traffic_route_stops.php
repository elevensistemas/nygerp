<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('traffic_route_stops', function (Blueprint $table) {
            $table->foreignId('traffic_loose_stop_id')
                ->nullable()
                ->after('order_id')
                ->constrained('traffic_loose_stops')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('traffic_route_stops', function (Blueprint $table) {
            $table->dropConstrainedForeignId('traffic_loose_stop_id');
        });
    }
};
