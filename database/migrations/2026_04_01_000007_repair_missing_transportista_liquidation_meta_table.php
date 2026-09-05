<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('transportista_liquidation_meta')) {
            Schema::create('transportista_liquidation_meta', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('transportista_id');
                $table->string('titular')->nullable();
                $table->string('patente')->nullable();
                $table->string('modelo')->nullable();
                $table->string('unidad')->nullable();
                $table->string('plaza')->nullable();
                $table->json('extra')->nullable();
                $table->timestamps();

                $table->foreign('transportista_id')->references('id')->on('transportistas')->cascadeOnDelete();
                $table->unique('transportista_id');
                $table->index(['patente', 'titular']);
            });
        }
    }

    public function down(): void
    {
        // idempotent repair migration
    }
};
