<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCostCentersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
   public function up(){
        Schema::create('cost_centers', function(Blueprint $t){
        $t->id();
        $t->string('code')->unique(); // AMBA, CBA, etc.
        $t->string('name'); // AMBA Depósito, Córdoba, etc.
        $t->timestamps();
        });
    }
    public function down(){ Schema::dropIfExists('cost_centers'); }
}
