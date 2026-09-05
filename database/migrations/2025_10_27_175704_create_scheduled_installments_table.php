<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateScheduledInstallmentsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(){
        Schema::create('scheduled_installments', function(Blueprint $t){
        $t->id();
        $t->foreignId('document_id')->constrained('documents')->cascadeOnDelete(); // Facturas con CC
        $t->integer('installment_number');
        $t->date('due_date');
        $t->decimal('amount',12,2);
        $t->boolean('paid')->default(false);
        $t->timestamps();
        $t->index(['due_date','paid']);
        });
    }
    public function down(){ Schema::dropIfExists('scheduled_installments'); }
}
