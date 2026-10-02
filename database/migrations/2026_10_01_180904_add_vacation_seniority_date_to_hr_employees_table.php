<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hr_employees', function (Blueprint $table) {
            $table->date('vacation_seniority_date')->nullable()->after('hire_date')->comment('Fecha de antigüedad reconocida para vacaciones (si difiere de hire_date)');
        });
    }

    public function down(): void
    {
        Schema::table('hr_employees', function (Blueprint $table) {
            $table->dropColumn('vacation_seniority_date');
        });
    }
};
