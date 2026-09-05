<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddInstallmentIdToAllocationsTable extends Migration
{
    public function up(): void
    {
        Schema::table('allocations', function (Blueprint $table) {
            // Nullable because existing allocations may not reference a specific installment
            $table->foreignId('installment_id')->nullable()->constrained('scheduled_installments')->nullOnDelete()->after('target_document_id');
        });
    }

    public function down(): void
    {
        Schema::table('allocations', function (Blueprint $table) {
            $table->dropForeign(['installment_id']);
            $table->dropColumn('installment_id');
        });
    }
}
