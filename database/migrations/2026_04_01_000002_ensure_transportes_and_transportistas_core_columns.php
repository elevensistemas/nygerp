<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('transportes') && !Schema::hasColumn('transportes', 'is_default')) {
            Schema::table('transportes', function (Blueprint $table) {
                $table->boolean('is_default')->default(false)->after('is_active');
            });
        }

        if (Schema::hasTable('transportistas') && !Schema::hasColumn('transportistas', 'color')) {
            Schema::table('transportistas', function (Blueprint $table) {
                $table->string('color', 20)->nullable()->after('notes');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('transportes') && Schema::hasColumn('transportes', 'is_default')) {
            Schema::table('transportes', function (Blueprint $table) {
                $table->dropColumn('is_default');
            });
        }

        if (Schema::hasTable('transportistas') && Schema::hasColumn('transportistas', 'color')) {
            Schema::table('transportistas', function (Blueprint $table) {
                $table->dropColumn('color');
            });
        }
    }
};
