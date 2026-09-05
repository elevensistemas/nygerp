<?php

namespace Database\Seeders;

use App\Models\DriverLiquidationSetting;
use Illuminate\Database\Seeder;

class DriverLiquidationSettingsSeeder extends Seeder
{
    public function run(): void
    {
        DriverLiquidationSetting::firstOrCreate([
            'tipo_periodo' => 'quincenal',
            'quincena' => 1,
        ], [
            'desde' => 1,
            'hasta' => 14,
            'activo' => true,
        ]);

        DriverLiquidationSetting::firstOrCreate([
            'tipo_periodo' => 'quincenal',
            'quincena' => 2,
        ], [
            'desde' => 15,
            'hasta' => 31,
            'activo' => true,
        ]);

        DriverLiquidationSetting::firstOrCreate([
            'tipo_periodo' => 'mensual',
            'quincena' => null,
        ], [
            'desde' => 1,
            'hasta' => 31,
            'activo' => true,
        ]);
    }
}
