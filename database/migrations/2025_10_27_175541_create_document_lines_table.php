<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDocumentLinesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(){
        Schema::create('document_lines', function(Blueprint $t){
        $t->id();
        $t->foreignId('document_id')->constrained('documents')->cascadeOnDelete();
        $t->string('concept'); // Ej: "Servicio ruta AMBA"
        $t->decimal('qty',12,2)->default(1);
        $t->decimal('price',12,2);
        $t->decimal('line_total',12,2);
        $t->foreignId('account_id')->nullable()->constrained('accounts');
        $t->foreignId('cost_center_id')->nullable()->constrained('cost_centers');
        $t->timestamps();
        });
    }
    public function down(){ Schema::dropIfExists('document_lines'); }
}
