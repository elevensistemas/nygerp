<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrafficRouteAssignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'traffic_route_id',
        'transportista_id',
        'transporte_id',
        'assigned_by',
        'assigned_at',
        'completed_stops',
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
    ];

    public function route(): BelongsTo
    {
        return $this->belongsTo(TrafficRoute::class, 'traffic_route_id');
    }

    public function transportista(): BelongsTo
    {
        return $this->belongsTo(Transportista::class);
    }

    public function transporte(): BelongsTo
    {
        return $this->belongsTo(Transporte::class);
    }
}
