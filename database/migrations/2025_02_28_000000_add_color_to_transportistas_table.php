<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('transportistas') || Schema::hasColumn('transportistas', 'color')) {
            return;
        }

        Schema::table('transportistas', function (Blueprint $table) {
            $table->string('color', 20)->nullable()->after('performance_weight');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('transportistas') || !Schema::hasColumn('transportistas', 'color')) {
            return;
        }

        Schema::table('transportistas', function (Blueprint $table) {
            $table->dropColumn('color');
        });
    }
};
