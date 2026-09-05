<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('settlement_rules', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('settlement_rule_conditions', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('settlement_rule_actions', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('settlement_rules', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('settlement_rule_conditions', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('settlement_rule_actions', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
