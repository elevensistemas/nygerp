<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transportistas', function (Blueprint $table) {
            if (! Schema::hasColumn('transportistas', 'bank_id')) {
                $table->unsignedBigInteger('bank_id')->nullable()->after('cbu');
            }

            if (! Schema::hasColumn('transportistas', 'account_number')) {
                $table->string('account_number', 120)->nullable()->after('bank_id');
            }
        });

        if (Schema::hasTable('banks') && Schema::hasColumn('transportistas', 'bank_id')) {
            try {
                Schema::table('transportistas', function (Blueprint $table) {
                    $table->foreign('bank_id')->references('id')->on('banks')->nullOnDelete();
                });
            } catch (\Throwable $e) {
                // Foreign key may already exist in some environments.
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('transportistas', 'bank_id')) {
            try {
                Schema::table('transportistas', function (Blueprint $table) {
                    $table->dropForeign(['bank_id']);
                });
            } catch (\Throwable $e) {
                // Ignore when the FK was not created.
            }
        }

        Schema::table('transportistas', function (Blueprint $table) {
            $dropColumns = [];

            if (Schema::hasColumn('transportistas', 'bank_id')) {
                $dropColumns[] = 'bank_id';
            }

            if (Schema::hasColumn('transportistas', 'account_number')) {
                $dropColumns[] = 'account_number';
            }

            if (! empty($dropColumns)) {
                $table->dropColumn($dropColumns);
            }
        });
    }
};
