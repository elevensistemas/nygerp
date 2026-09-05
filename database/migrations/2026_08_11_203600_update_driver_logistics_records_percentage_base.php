<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::table('driver_logistics_records')
            ->where('porcentaje', '<=', 1.0)
            ->update([
                'porcentaje' => DB::raw('porcentaje * 100')
            ]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::table('driver_logistics_records')
            ->update([
                'porcentaje' => DB::raw('porcentaje / 100')
            ]);
    }
};
