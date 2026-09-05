<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePaymentsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(){
        Schema::create('payments', function(Blueprint $t){
        $t->id();
        $t->foreignId('document_id')->constrained('documents')->cascadeOnDelete(); // Recibo/OP
        $t->enum('method',['bank_transfer','cash','withholding','advance']);
        $t->string('reference')->nullable(); // nro transferencia, etc.
        $t->decimal('amount',12,2);
        $t->date('paid_at');
        $t->timestamps();
        });
    }
    public function down(){ Schema::dropIfExists('payments'); }
}
