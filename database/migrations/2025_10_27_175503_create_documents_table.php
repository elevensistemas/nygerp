<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDocumentsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(){
        Schema::create('documents', function(Blueprint $t){
        $t->id();
        $t->enum('scope',['purchase','sale']);
        $t->enum('doctype',['invoice','credit_note','debit_note','receipt','payment_order','fund_movement']);
        $t->string('number'); // prefijo-numero
        $t->foreignId('party_id')->constrained('parties');
        $t->date('issue_date');
        $t->foreignId('payment_term_id')->nullable()->constrained('payment_terms');
        $t->decimal('subtotal',12,2)->default(0);
        $t->decimal('tax',12,2)->default(0);
        $t->decimal('total',12,2)->default(0);
        $t->boolean('affects_ledger')->default(true); // Algunos movimientos operativos podrían no afectar
        $t->boolean('affects_current_account')->default(true); // facturas sí, pagos instantáneos no
        $t->text('notes')->nullable();
        $t->timestamps();
        $t->index(['scope','doctype','number']);
        });
    }
    public function down(){ Schema::dropIfExists('documents'); }
}
