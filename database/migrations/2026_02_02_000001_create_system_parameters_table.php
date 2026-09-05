<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_parameters', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('label');
            $table->text('description')->nullable();
            $table->string('value')->nullable();
            $table->timestamps();
        });

        DB::table('system_parameters')->insert([
            'key' => 'valida_transportista',
            'label' => 'Valida transportista',
            'description' => 'Controla si al crear transportistas se envía el email de aceptación de términos.',
            'value' => '1',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('system_parameters');
    }
};
