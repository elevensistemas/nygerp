<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('driver_import_run_rows') && Schema::hasColumn('driver_import_run_rows', 'message')) {
            Schema::table('driver_import_run_rows', function (Blueprint $table) {
                $table->text('message')->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('driver_import_run_rows') && Schema::hasColumn('driver_import_run_rows', 'message')) {
            Schema::table('driver_import_run_rows', function (Blueprint $table) {
                $table->string('message')->nullable()->change();
            });
        }
    }
};
