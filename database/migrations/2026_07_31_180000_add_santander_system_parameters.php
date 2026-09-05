<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class AddSantanderSystemParameters extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::table('system_parameters')->insert([
            [
                'key' => 'company_cuit',
                'label' => 'CUIT de la Empresa',
                'description' => 'CUIT de la empresa para la generación de la importación bancaria (11 dígitos sin guiones).',
                'value' => '30123456789', // Default placeholder
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'santander_agreement_number',
                'label' => 'Número de Acuerdo Santander',
                'description' => 'Número de acuerdo asignado por el Banco Santander para transferencias de pagos (2 dígitos).',
                'value' => '01', // Default placeholder
                'created_at' => now(),
                'updated_at' => now(),
            ]
        ]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::table('system_parameters')
            ->whereIn('key', ['company_cuit', 'santander_agreement_number'])
            ->delete();
    }
}
