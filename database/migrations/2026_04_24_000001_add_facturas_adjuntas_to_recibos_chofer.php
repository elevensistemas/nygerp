<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class AddFacturasAdjuntasToRecibosChofer extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('recibos_chofer', function (Blueprint $table) {
            $table->json('facturas_adjuntas')->nullable()->after('factura_pdf_nombre');
        });

        // Migrate existing single PDFs to the new JSON array column
        $recibos = DB::table('recibos_chofer')
            ->whereNotNull('factura_pdf_path')
            ->get(['id', 'factura_pdf_path', 'factura_pdf_nombre']);

        foreach ($recibos as $recibo) {
            $adjuntos = [
                [
                    'path' => $recibo->factura_pdf_path,
                    'nombre' => $recibo->factura_pdf_nombre,
                    'uploaded_at' => now()->toDateTimeString()
                ]
            ];
            
            DB::table('recibos_chofer')
                ->where('id', $recibo->id)
                ->update(['facturas_adjuntas' => json_encode($adjuntos)]);
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('recibos_chofer', function (Blueprint $table) {
            $table->dropColumn('facturas_adjuntas');
        });
    }
}
