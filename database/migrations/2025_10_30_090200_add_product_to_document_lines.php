<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('document_lines', function (Blueprint $table) {
            $table->foreignId('product_id')
                ->nullable()
                ->after('document_id')
                ->constrained('products')
                ->nullOnDelete();
            $table->string('unit', 25)->nullable()->after('qty');
        });
    }

    public function down(): void
    {
        Schema::table('document_lines', function (Blueprint $table) {
            if (Schema::hasColumn('document_lines', 'product_id')) {
                $table->dropForeign(['product_id']);
                $table->dropColumn('product_id');
            }
            if (Schema::hasColumn('document_lines', 'unit')) {
                $table->dropColumn('unit');
            }
        });
    }
};
