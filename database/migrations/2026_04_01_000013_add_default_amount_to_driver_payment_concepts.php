<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('driver_payment_concepts') && ! Schema::hasColumn('driver_payment_concepts', 'default_amount')) {
            Schema::table('driver_payment_concepts', function (Blueprint $table) {
                $table->decimal('default_amount', 14, 2)->nullable()->after('name');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('driver_payment_concepts') && Schema::hasColumn('driver_payment_concepts', 'default_amount')) {
            Schema::table('driver_payment_concepts', function (Blueprint $table) {
                $table->dropColumn('default_amount');
            });
        }
    }
};

