<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrafficRouteStopEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'traffic_route_stop_id',
        'user_id',
        'status',
        'delivery_reason_id',
        'recipient_name',
        'recipient_dni',
        'recipient_is_owner',
        'status_notes',
        'happened_at',
    ];

    protected $casts = [
        'recipient_is_owner' => 'boolean',
        'happened_at' => 'datetime',
    ];

    public function stop(): BelongsTo
    {
        return $this->belongsTo(TrafficRouteStop::class, 'traffic_route_stop_id');
    }

    public function deliveryReason(): BelongsTo
    {
        return $this->belongsTo(DeliveryReason::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
