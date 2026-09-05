<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
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
                $table->string('portal_visibility')->default('all')->after('is_active');
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
            if (Schema::hasColumn('transportistas', 'portal_visibility')) {
                $table->dropColumn('portal_visibility');
            }
        });
    }
};
