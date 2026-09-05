<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transportista_import_formats', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120)->nullable();
            $table->string('sheet', 120)->nullable();
            $table->unsignedInteger('start_row')->default(2);
            $table->json('field_mappings')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transportista_import_formats');
    }
};
