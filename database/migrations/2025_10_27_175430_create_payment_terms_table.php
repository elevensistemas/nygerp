<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePaymentTermsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(){
        Schema::create('payment_terms', function(Blueprint $t){
        $t->id();
        $t->string('name'); // 30-60-90, Quincenal, Mensual
        $t->json('days')->nullable(); // [30,60,90] o [15,30]
        $t->timestamps();
        });
    }
    public function down(){ Schema::dropIfExists('payment_terms'); }
}
