<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('traffic_loose_stops', function (Blueprint $table) {
            $table->string('qr_code', 255)->nullable()->after('barcode');
        });
    }

    public function down(): void
    {
        Schema::table('traffic_loose_stops', function (Blueprint $table) {
            $table->dropColumn('qr_code');
        });
    }
};
