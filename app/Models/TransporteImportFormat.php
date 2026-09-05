<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TransporteImportFormat extends Model
{
    protected $fillable = [
        'name',
        'sheet',
        'start_row',
        'field_mappings',
    ];

    protected $casts = [
        'start_row' => 'integer',
        'field_mappings' => 'array',
    ];

    public static function fieldLabels(): array
    {
        return [
            'driver_name' => 'Chofer',
            'owner_name' => 'Titular',
            'status' => 'Estado',
            'license_plate' => 'Patente',
            'type' => 'Tipo',
            'unit_color' => 'Color de la unidad',
            'brand' => 'Marca',
            'model' => 'Modelo',
            'version' => 'Versión',
            'year' => 'Año',
            'chassis_number' => 'Nº de chasis',
            'fuel_type' => 'Combustible',
            'registration_card' => 'Cédula verde/azul',
            'vtv' => 'VTV',
            'insurance' => 'Seguro',
            'satellite' => 'Satelital',
            'doors_count' => 'Cant. de puertas',
            'tank_capacity' => 'Cap. tanque',
            'fuel_consumption_avg' => 'Prom. consumo de comb.',
        ];
    }
}
