<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAccountsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
   public function up(){
        Schema::create('accounts', function(Blueprint $t){
        $t->id();
        $t->string('code')->unique(); // 1.1.01
        $t->string('name'); // Caja, Bancos, Proveedores, Ingresos, etc.
        $t->enum('type',['asset','liability','equity','income','expense']);
        $t->timestamps();
        });
    }
    public function down(){ Schema::dropIfExists('accounts'); }
}
