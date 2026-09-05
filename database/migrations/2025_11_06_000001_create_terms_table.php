<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('terms', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->text('content');
            $table->timestamps();
        });

        DB::table('terms')->insert([
            'slug' => 'terms-of-use',
            'content' => 'Aquí puedes redactar los términos y condiciones y la política de privacidad de tu organización. Reemplaza este texto por lo que definirás para tus usuarios.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('terms');
    }
};
