<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddDimensionsToTransportesTable extends Migration
{
    public function up()
    {
        Schema::table('transportes', function (Blueprint $table) {
            $table->decimal('length_cm', 10, 2)->nullable()->after('capacity_kg');
            $table->decimal('width_cm', 10, 2)->nullable()->after('length_cm');
            $table->decimal('height_cm', 10, 2)->nullable()->after('width_cm');
            $table->decimal('volume_m3', 10, 3)->nullable()->after('height_cm');
        });
    }

    public function down()
    {
        Schema::table('transportes', function (Blueprint $table) {
            $table->dropColumn(['length_cm', 'width_cm', 'height_cm', 'volume_m3']);
        });
    }
}
