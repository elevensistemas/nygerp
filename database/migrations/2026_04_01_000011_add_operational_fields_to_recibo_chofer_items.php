<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('recibo_chofer_items')) {
            return;
        }

        Schema::table('recibo_chofer_items', function (Blueprint $table) {
            if (! Schema::hasColumn('recibo_chofer_items', 'zona')) {
                $table->string('zona')->nullable()->after('cantidad');
            }
            if (! Schema::hasColumn('recibo_chofer_items', 'paradas')) {
                $table->decimal('paradas', 14, 3)->nullable()->after('zona');
            }
            if (! Schema::hasColumn('recibo_chofer_items', 'paquetes')) {
                $table->decimal('paquetes', 14, 3)->nullable()->after('paradas');
            }
            if (! Schema::hasColumn('recibo_chofer_items', 'entregados')) {
                $table->decimal('entregados', 14, 3)->nullable()->after('paquetes');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('recibo_chofer_items')) {
            return;
        }

        Schema::table('recibo_chofer_items', function (Blueprint $table) {
            $columns = ['entregados', 'paquetes', 'paradas', 'zona'];
            foreach ($columns as $column) {
                if (Schema::hasColumn('recibo_chofer_items', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};

