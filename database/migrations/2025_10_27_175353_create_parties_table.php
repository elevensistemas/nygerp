<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePartiesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(){
        Schema::create('parties', function(Blueprint $t){
        $t->id();
        $t->enum('role',['customer','supplier','driver','owner']);
        $t->string('name');
        $t->string('tax_id')->nullable(); // CUIT/DNI
        $t->string('email')->nullable();
        $t->string('bank_alias')->nullable();
        $t->string('bank_cbu')->nullable();
        $t->boolean('active')->default(true);
        $t->timestamps();
        });
    }
    public function down(){ Schema::dropIfExists('parties'); }
}
