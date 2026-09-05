<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAllocationsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(){
        Schema::create('allocations', function(Blueprint $t){
        $t->id();
        $t->foreignId('document_id')->constrained('documents')->cascadeOnDelete(); // NC/ND/OP que imputan
        $t->foreignId('target_document_id')->constrained('documents'); // A qué factura aplica
        $t->decimal('amount',12,2);
        $t->timestamps();
        $t->unique(['document_id','target_document_id']);
        });
    }
    public function down(){ Schema::dropIfExists('allocations'); }
}
