<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('parties', function (Blueprint $table) {
            $table->string('business_name')->nullable()->after('name');
            $table->enum('iva_condition', [
                'responsable_inscripto',
                'monotributo',
                'exento',
                'consumidor_final',
                'no_residente'
            ])->nullable()->after('business_name');
            $table->string('iibb_number')->nullable()->after('iva_condition');
            $table->string('phone')->nullable()->after('iibb_number');
            $table->string('mobile')->nullable()->after('phone');
            $table->string('address')->nullable()->after('mobile');
            $table->string('city')->nullable()->after('address');
            $table->string('province')->nullable()->after('city');
            $table->string('postal_code')->nullable()->after('province');
            $table->string('email_secondary')->nullable()->after('email');
            $table->text('notes')->nullable()->after('bank_cbu');
        });
    }

    public function down(): void
    {
        Schema::table('parties', function (Blueprint $table) {
            $table->dropColumn([
                'business_name',
                'iva_condition',
                'iibb_number',
                'phone',
                'mobile',
                'address',
                'city',
                'province',
                'postal_code',
                'email_secondary',
                'notes',
            ]);
        });
    }
};
