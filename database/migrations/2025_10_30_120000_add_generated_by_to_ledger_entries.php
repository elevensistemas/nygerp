<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('ledger_entries', function (Blueprint $table) {
            $table->string('generated_by', 20)
                ->default('manual')
                ->after('description');
            $table->index('generated_by');
        });
    }

    public function down(): void
    {
        Schema::table('ledger_entries', function (Blueprint $table) {
            if (Schema::hasColumn('ledger_entries', 'generated_by')) {
                $table->dropIndex(['generated_by']);
                $table->dropColumn('generated_by');
            }
        });
    }
};
