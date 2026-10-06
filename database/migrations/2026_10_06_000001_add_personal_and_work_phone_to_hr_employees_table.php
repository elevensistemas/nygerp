<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddPersonalAndWorkPhoneToHrEmployeesTable extends Migration
{
    public function up()
    {
        Schema::table('hr_employees', function (Blueprint $table) {
            if (!Schema::hasColumn('hr_employees', 'personal_phone')) {
                $table->string('personal_phone', 50)->nullable()->after('marital_status');
            }
            if (!Schema::hasColumn('hr_employees', 'work_phone')) {
                $table->string('work_phone', 50)->nullable()->after('personal_phone');
            }
        });

        // Copiar valor de phone existente a personal_phone si personal_phone es nulo
        if (Schema::hasColumn('hr_employees', 'phone') && Schema::hasColumn('hr_employees', 'personal_phone')) {
            DB::statement("UPDATE hr_employees SET personal_phone = phone WHERE personal_phone IS NULL AND phone IS NOT NULL AND phone != ''");
        }
    }

    public function down()
    {
        Schema::table('hr_employees', function (Blueprint $table) {
            if (Schema::hasColumn('hr_employees', 'work_phone')) {
                $table->dropColumn('work_phone');
            }
            if (Schema::hasColumn('hr_employees', 'personal_phone')) {
                $table->dropColumn('personal_phone');
            }
        });
    }
}
