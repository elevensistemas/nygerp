<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transportistas', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('name')->constrained()->nullOnDelete()->unique();
        });
    }

    public function down(): void
    {
        Schema::table('transportistas', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropColumn('user_id');
        });
    }
};
