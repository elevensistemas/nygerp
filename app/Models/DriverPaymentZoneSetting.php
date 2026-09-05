<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DriverPaymentZoneSetting extends Model
{
    use HasFactory;

    public const TYPE_ZONA = 'ZONA';
    public const TYPE_KM = 'KM';
    public const TYPE_PAQUETE = 'PAQUETE';

    protected $fillable = [
        'traffic_zone_id',
        'calc_type',
        'package_rate',
        'km_package_threshold',
        'km_excess_package_amount',
        'km_remote_zone_plus_large',
        'package_delivered_rate',
        'package_absent_rate_multiplier',
        'package_fixed_amount',
        'package_excess_threshold',
        'package_excess_amount',
    ];

    protected $casts = [
        'package_rate' => 'decimal:4',
        'km_package_threshold' => 'integer',
        'km_excess_package_amount' => 'decimal:2',
        'km_remote_zone_plus_large' => 'decimal:2',
        'package_delivered_rate' => 'decimal:4',
        'package_absent_rate_multiplier' => 'decimal:4',
        'package_fixed_amount' => 'decimal:2',
        'package_excess_threshold' => 'integer',
        'package_excess_amount' => 'decimal:2',
    ];

    public function zone(): BelongsTo
    {
        return $this->belongsTo(TrafficZone::class, 'traffic_zone_id');
    }
}
