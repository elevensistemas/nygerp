<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DriverPaymentAdjustmentRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'amount',
        'sign',
        'traffic_zone_id',
        'transportista_id',
        'vehicle_type',
        'active',
        'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'sign' => 'integer',
        'active' => 'boolean',
    ];

    public function zone(): BelongsTo
    {
        return $this->belongsTo(TrafficZone::class, 'traffic_zone_id');
    }

    public function transportista(): BelongsTo
    {
        return $this->belongsTo(Transportista::class, 'transportista_id');
    }
}
