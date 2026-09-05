<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DriverPaymentKmRange extends Model
{
    use HasFactory;

    protected $fillable = [
        'traffic_zone_id',
        'vehicle_type',
        'km_from',
        'km_to',
        'amount',
        'active',
    ];

    protected $casts = [
        'km_from' => 'decimal:3',
        'km_to' => 'decimal:3',
        'amount' => 'decimal:2',
        'active' => 'boolean',
    ];

    public function zone(): BelongsTo
    {
        return $this->belongsTo(TrafficZone::class, 'traffic_zone_id');
    }
}
