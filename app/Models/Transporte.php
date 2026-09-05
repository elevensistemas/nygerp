<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Transporte extends Model
{
    use HasFactory;

    public const TYPE_MOTO = 'moto';
    public const TYPE_CAMIONETA = 'camioneta';
    public const TYPE_CAMIONETA_MEDIANA = 'camioneta_mediana';
    public const TYPE_CAMIONETA_GRANDE = 'camioneta_grande';

    protected $fillable = [
        'transportista_id',
        'driver_name',
        'owner_name',
        'status',
        'alias',
        'license_plate',
        'type',
        'unit_color',
        'brand',
        'model',
        'version',
        'year',
        'chassis_number',
        'fuel_type',
        'registration_card',
        'vtv',
        'insurance',
        'satellite',
        'doors_count',
        'tank_capacity',
        'fuel_consumption_avg',
        'capacity_kg',
        'length_cm',
        'width_cm',
        'height_cm',
        'volume_m3',
        'tracking_identifier',
        'is_active',
        'is_default',
        'notes',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_default' => 'boolean',
        'length_cm' => 'float',
        'width_cm' => 'float',
        'height_cm' => 'float',
        'volume_m3' => 'float',
        'doors_count' => 'integer',
    ];

    public function transportista(): BelongsTo
    {
        return $this->belongsTo(Transportista::class);
    }

    public function routes(): HasMany
    {
        return $this->hasMany(TrafficRoute::class);
    }

    public static function paymentVehicleTypes(bool $includeGeneral = false): array
    {
        $setting = \App\Models\DriverImportSetting::where('setting_key', 'vehicle_type_aliases')->first();
        $aliases = is_array(optional($setting)->setting_value) ? $setting->setting_value : [];

        $types = [
            self::TYPE_MOTO => $aliases[self::TYPE_MOTO] ?? 'Moto',
            self::TYPE_CAMIONETA => $aliases[self::TYPE_CAMIONETA] ?? 'Camioneta',
            self::TYPE_CAMIONETA_MEDIANA => $aliases[self::TYPE_CAMIONETA_MEDIANA] ?? 'Camioneta mediana',
            self::TYPE_CAMIONETA_GRANDE => $aliases[self::TYPE_CAMIONETA_GRANDE] ?? 'Camioneta grande',
        ];

        if ($includeGeneral) {
            return ['general' => $aliases['general'] ?? 'General'] + $types;
        }

        return $types;
    }
}
