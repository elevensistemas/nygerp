<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('traffic_loose_stop_import_formats', function (Blueprint $table) {
            $table->boolean('address_includes_locality')->default(false)->after('start_row');
        });
    }

    public function down(): void
    {
        Schema::table('traffic_loose_stop_import_formats', function (Blueprint $table) {
            $table->dropColumn('address_includes_locality');
        });
    }
};
