<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('scheduled_installments', function (Blueprint $table) {
            // Si no existe, agregamos 'paid' también
            if (!Schema::hasColumn('scheduled_installments', 'paid')) {
                $table->boolean('paid')->default(false)->after('amount');
            }
            // Nueva columna paid_at
            $table->timestamp('paid_at')->nullable()->after('paid');
        });
    }

    public function down(): void
    {
        Schema::table('scheduled_installments', function (Blueprint $table) {
            if (Schema::hasColumn('scheduled_installments', 'paid_at')) {
                $table->dropColumn('paid_at');
            }
            // Si la agregaste acá, podés optar por removerla en down()
            // if (Schema::hasColumn('scheduled_installments', 'paid')) {
            //     $table->dropColumn('paid');
            // }
        });
    }
};
