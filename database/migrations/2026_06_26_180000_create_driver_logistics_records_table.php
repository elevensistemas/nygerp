<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('driver_logistics_records', function (Blueprint $table) {
            $table->id();
            $table->date('fecha');
            $table->foreignId('transportista_id')->constrained('transportistas')->onDelete('cascade');
            $table->foreignId('transporte_id')->nullable()->constrained('transportes')->onDelete('set null');
            $table->string('svc')->nullable();
            $table->string('ruta')->nullable();
            $table->string('numero')->nullable();
            $table->string('zona')->nullable();
            $table->integer('paradas')->default(0);
            $table->integer('paquetes')->default(0);
            $table->integer('entregados')->default(0);
            $table->integer('deja_en_svc')->default(0);
            $table->integer('paq_no_colectado')->default(0);
            $table->integer('nadie_en_domicilio')->default(0);
            $table->integer('negocio_cerrado')->default(0);
            $table->integer('qr')->default(0);
            $table->integer('fuera_de_zona')->default(0);
            $table->integer('zona_inaccesible')->default(0);
            $table->integer('rechazado')->default(0);
            $table->integer('sin_visitar')->default(0);
            $table->integer('fraude')->default(0);
            $table->integer('paquete_perdido')->default(0);
            $table->integer('paquete_danado')->default(0);
            $table->integer('paquete_robado')->default(0);
            $table->decimal('porcentaje', 5, 2)->default(0);
            $table->decimal('kilometros', 8, 2)->default(0);
            $table->decimal('kilometros_estimados', 8, 2)->default(0);
            $table->boolean('zona_lejana')->default(false);
            $table->text('observacion')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('driver_logistics_records');
    }
};
