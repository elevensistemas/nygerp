<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIsDefaultToTransportesTable extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('transportes') || Schema::hasColumn('transportes', 'is_default')) {
            return;
        }

        Schema::table('transportes', function (Blueprint $table) {
            $table->boolean('is_default')->default(false)->after('is_active');
        });
    }

    public function down()
    {
        if (!Schema::hasTable('transportes') || !Schema::hasColumn('transportes', 'is_default')) {
            return;
        }

        Schema::table('transportes', function (Blueprint $table) {
            $table->dropColumn('is_default');
        });
    }
}
