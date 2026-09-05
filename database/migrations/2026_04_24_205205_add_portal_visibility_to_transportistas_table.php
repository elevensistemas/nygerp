<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPortalVisibilityToTransportistasTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('transportistas', function (Blueprint $table) {
            if (!Schema::hasColumn('transportistas', 'portal_visibility')) {
                $table->string('portal_visibility')->default('all')->after('notes');
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('transportistas', function (Blueprint $table) {
            $table->dropColumn('portal_visibility');
        });
    }
}
