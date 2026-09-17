<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('driver_logistics_records', function (Blueprint $table) {
            $table->index(['fecha', 'transportista_id', 'traffic_zone_id'], 'idx_dlr_fecha_transp_zone');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('driver_logistics_records', function (Blueprint $table) {
            $table->dropIndex('idx_dlr_fecha_transp_zone');
        });
    }
};
