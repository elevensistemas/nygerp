<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddDriverStatusToReciboChoferItemsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('recibo_chofer_items', function (Blueprint $table) {
            $table->string('driver_status')->nullable()->after('meta');
            $table->text('driver_comment')->nullable()->after('driver_status');
            $table->timestamp('driver_status_date')->nullable()->after('driver_comment');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('recibo_chofer_items', function (Blueprint $table) {
            $table->dropColumn(['driver_status', 'driver_comment', 'driver_status_date']);
        });
    }
}
