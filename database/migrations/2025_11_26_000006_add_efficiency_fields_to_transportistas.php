<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('transportistas', function (Blueprint $table) {
            $table->decimal('cost_efficiency', 6, 2)
                ->default(1)
                ->after('supplier_id')
                ->comment('Eficiencia de costo relativa del transportista');
            $table->decimal('performance_weight', 6, 2)
                ->default(1)
                ->after('cost_efficiency')
                ->comment('Ponderacion de eficiencia general del transportista');
        });
    }

    public function down(): void
    {
        Schema::table('transportistas', function (Blueprint $table) {
            $table->dropColumn(['cost_efficiency', 'performance_weight']);
        });
    }
};
