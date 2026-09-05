<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DriverPaymentZoneConceptYearValue extends Model
{
    use HasFactory;

    protected $fillable = [
        'traffic_zone_id',
        'driver_payment_concept_id',
        'vehicle_type',
        'year_from',
        'year_to',
        'amount',
    ];

    protected $casts = [
        'year_from' => 'integer',
        'year_to' => 'integer',
        'amount' => 'decimal:2',
    ];

    public function zone(): BelongsTo
    {
        return $this->belongsTo(TrafficZone::class, 'traffic_zone_id');
    }

    public function concept(): BelongsTo
    {
        return $this->belongsTo(DriverPaymentConcept::class, 'driver_payment_concept_id');
    }
}
