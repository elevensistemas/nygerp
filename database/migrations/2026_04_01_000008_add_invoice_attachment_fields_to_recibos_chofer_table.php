<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recibos_chofer', function (Blueprint $table) {
            if (! Schema::hasColumn('recibos_chofer', 'factura_pdf_path')) {
                $table->string('factura_pdf_path')->nullable()->after('factura_ref');
            }

            if (! Schema::hasColumn('recibos_chofer', 'factura_pdf_nombre')) {
                $table->string('factura_pdf_nombre')->nullable()->after('factura_pdf_path');
            }

            if (! Schema::hasColumn('recibos_chofer', 'factura_fecha')) {
                $table->date('factura_fecha')->nullable()->after('factura_pdf_nombre');
            }

            if (! Schema::hasColumn('recibos_chofer', 'factura_observaciones')) {
                $table->text('factura_observaciones')->nullable()->after('factura_fecha');
            }
        });
    }

    public function down(): void
    {
        Schema::table('recibos_chofer', function (Blueprint $table) {
            $dropColumns = [];

            foreach (['factura_pdf_path', 'factura_pdf_nombre', 'factura_fecha', 'factura_observaciones'] as $column) {
                if (Schema::hasColumn('recibos_chofer', $column)) {
                    $dropColumns[] = $column;
                }
            }

            if (! empty($dropColumns)) {
                $table->dropColumn($dropColumns);
            }
        });
    }
};
