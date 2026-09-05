<?php

namespace App\Models;

use App\Models\TrafficRouteStop;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrafficRouteStopPhoto extends Model
{
    use HasFactory;

    protected $fillable = [
        'traffic_route_stop_id',
        'user_id',
        'path',
        'filename',
        'mimetype',
        'size',
    ];

    public function stop(): BelongsTo
    {
        return $this->belongsTo(TrafficRouteStop::class, 'traffic_route_stop_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
