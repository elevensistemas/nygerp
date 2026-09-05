<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Si ya existe y no es json, la cambiamos a json (en MySQL 5.7+)
        Schema::table('payment_terms', function (Blueprint $table) {
            // Algunas instalaciones requieren dropColumn previo + addColumn.
            // Si falla "cannot change column", hacé un dump y volvé a crear.
            $table->json('days')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('payment_terms', function (Blueprint $table) {
            $table->text('days')->nullable()->change();
        });
    }
};
