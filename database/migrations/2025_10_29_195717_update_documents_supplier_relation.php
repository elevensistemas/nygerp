<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $t) {
            // 1️⃣ Eliminar la foreign key anterior (party_id)
            if (Schema::hasColumn('documents', 'party_id')) {
                $t->dropForeign(['party_id']);
                $t->dropColumn('party_id');
            }

            // 2️⃣ Agregar nueva foreign key a suppliers
            $t->foreignId('supplier_id')
              ->nullable()
              ->after('number')
              ->constrained('suppliers')
              ->cascadeOnUpdate()
              ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $t) {
            // Revertir cambios
            if (Schema::hasColumn('documents', 'supplier_id')) {
                $t->dropForeign(['supplier_id']);
                $t->dropColumn('supplier_id');
            }

            $t->foreignId('party_id')
              ->nullable()
              ->after('number')
              ->constrained('parties')
              ->cascadeOnUpdate()
              ->nullOnDelete();
        });
    }
};
