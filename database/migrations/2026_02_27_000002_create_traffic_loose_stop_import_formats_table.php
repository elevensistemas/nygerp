<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('traffic_loose_stop_import_formats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('party_id')->constrained('parties')->cascadeOnDelete();
            $table->string('name')->nullable();
            $table->string('sheet')->nullable();
            $table->unsignedSmallInteger('start_row')->default(5);
            $table->string('order_date_cell_column', 5)->nullable();
            $table->unsignedSmallInteger('order_date_cell_row')->nullable();
            $table->json('field_mappings')->nullable();
            $table->timestamps();

            $table->unique('party_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('traffic_loose_stop_import_formats');
    }
};
