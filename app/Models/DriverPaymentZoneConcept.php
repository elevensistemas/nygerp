<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DriverPaymentZoneConcept extends Model
{
    use HasFactory;

    protected $fillable = [
        'traffic_zone_id',
        'driver_payment_concept_id',
        'vehicle_type',
        'value_type',
        'monto_efectivo',
        'reference_concept_id',
        'reference_multiplier',
        'active',
    ];

    protected $casts = [
        'monto_efectivo' => 'decimal:2',
        'reference_multiplier' => 'decimal:4',
        'active' => 'boolean',
    ];

    public function trafficZone(): BelongsTo
    {
        return $this->belongsTo(TrafficZone::class, 'traffic_zone_id');
    }

    public function concept(): BelongsTo
    {
        return $this->belongsTo(DriverPaymentConcept::class, 'driver_payment_concept_id');
    }

    public function referenceConcept(): BelongsTo
    {
        return $this->belongsTo(DriverPaymentConcept::class, 'reference_concept_id');
    }
}
