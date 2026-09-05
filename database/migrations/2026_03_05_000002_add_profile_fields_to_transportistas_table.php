<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transportistas', function (Blueprint $table) {
            $table->string('status', 50)->nullable()->after('base_location');
            $table->string('condition', 80)->nullable()->after('status');
            $table->string('client_name', 255)->nullable()->after('condition');
            $table->string('phone_alt', 120)->nullable()->after('phone');
            $table->string('dni', 50)->nullable()->after('tax_id');
            $table->date('birth_date')->nullable()->after('dni');
            $table->date('license_expires_at')->nullable()->after('license_number');
            $table->string('personal_insurance', 120)->nullable()->after('license_expires_at');
            $table->boolean('address_certificate')->default(false)->after('personal_insurance');
            $table->boolean('criminal_record_certificate')->default(false)->after('address_certificate');
            $table->string('cbu', 120)->nullable()->after('criminal_record_certificate');
            $table->string('monotributo', 120)->nullable()->after('cbu');
            $table->date('hire_date')->nullable()->after('monotributo');
        });
    }

    public function down(): void
    {
        Schema::table('transportistas', function (Blueprint $table) {
            $table->dropColumn([
                'status',
                'condition',
                'client_name',
                'phone_alt',
                'dni',
                'birth_date',
                'license_expires_at',
                'personal_insurance',
                'address_certificate',
                'criminal_record_certificate',
                'cbu',
                'monotributo',
                'hire_date',
            ]);
        });
    }
};
