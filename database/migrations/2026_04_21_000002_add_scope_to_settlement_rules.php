<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('settlement_rules') || Schema::hasColumn('settlement_rules', 'scope')) {
            return;
        }

        Schema::table('settlement_rules', function (Blueprint $table) {
            $table->string('scope', 20)->default('line')->after('active');
            $table->index('scope', 'settlement_rules_scope_idx');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('settlement_rules') || ! Schema::hasColumn('settlement_rules', 'scope')) {
            return;
        }

        Schema::table('settlement_rules', function (Blueprint $table) {
            $table->dropIndex('settlement_rules_scope_idx');
            $table->dropColumn('scope');
        });
    }
};
