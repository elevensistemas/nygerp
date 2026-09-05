<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('traffic_loose_stop_import_formats', function (Blueprint $table) {
            if (!Schema::hasColumn('traffic_loose_stop_import_formats', 'order_date_cell_column')) {
                $table->string('order_date_cell_column', 5)->nullable()->after('start_row');
            }
            if (!Schema::hasColumn('traffic_loose_stop_import_formats', 'order_date_cell_row')) {
                $table->unsignedSmallInteger('order_date_cell_row')->nullable()->after('order_date_cell_column');
            }
        });
    }

    public function down(): void
    {
        Schema::table('traffic_loose_stop_import_formats', function (Blueprint $table) {
            if (Schema::hasColumn('traffic_loose_stop_import_formats', 'order_date_cell_row')) {
                $table->dropColumn('order_date_cell_row');
            }
            if (Schema::hasColumn('traffic_loose_stop_import_formats', 'order_date_cell_column')) {
                $table->dropColumn('order_date_cell_column');
            }
        });
    }
};
