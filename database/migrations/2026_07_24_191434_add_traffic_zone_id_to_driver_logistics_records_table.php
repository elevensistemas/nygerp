<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddTrafficZoneIdToDriverLogisticsRecordsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('driver_logistics_records', function (Blueprint $table) {
            $table->foreignId('traffic_zone_id')->nullable()->after('transporte_id')->constrained('traffic_zones')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('driver_logistics_records', function (Blueprint $table) {
            $table->dropForeign(['traffic_zone_id']);
            $table->dropColumn('traffic_zone_id');
        });
    }
}
