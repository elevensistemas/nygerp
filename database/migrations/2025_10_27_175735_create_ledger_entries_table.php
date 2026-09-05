<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateLedgerEntriesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(){
        Schema::create('ledger_entries', function(Blueprint $t){
        $t->id();
        $t->foreignId('document_id')->nullable()->constrained('documents');
        $t->date('entry_date');
        $t->foreignId('account_id')->constrained('accounts');
        $t->foreignId('cost_center_id')->nullable()->constrained('cost_centers');
        $t->decimal('debit',12,2)->default(0);
        $t->decimal('credit',12,2)->default(0);
        $t->string('description')->nullable();
        $t->timestamps();
        });
    }
    public function down(){ Schema::dropIfExists('ledger_entries'); }
}
