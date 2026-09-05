<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSettlementRulesTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('settlement_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->integer('priority')->default(0)->index();
            $table->boolean('is_modifier')->default(false)->index();
            $table->boolean('active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('settlement_rule_conditions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('settlement_rule_id')->constrained()->onDelete('cascade');
            $table->string('field', 50)->index();
            $table->string('operator', 20);
            $table->json('value');
            $table->timestamps();
        });

        Schema::create('settlement_rule_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('settlement_rule_id')->unique()->constrained()->onDelete('cascade');
            $table->string('action_type', 50)->index();
            $table->json('payload');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('settlement_rule_actions');
        Schema::dropIfExists('settlement_rule_conditions');
        Schema::dropIfExists('settlement_rules');
    }
}
