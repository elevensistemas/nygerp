<?php

namespace App\Models;

use App\Models\Order;
use App\Models\TrafficLooseStop;
use App\Models\TrafficRouteStop;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class TrafficRoute extends Model
{
    use HasFactory;

    protected $fillable = [
        'transportista_id',
        'transporte_id',
        'user_id',
        'code',
        'status',
        'scheduled_date',
        'distance_meters',
        'duration_seconds',
        'traffic_summary',
        'congestion_level',
        'geometry',
        'traffic_report',
        'raw_payload',
        'order_id',
        'started_at',
        'completed_at',
        'sent_at',
        'sent_by',
    ];

    protected $casts = [
        'scheduled_date' => 'date',
        'geometry' => 'array',
        'traffic_report' => 'array',
        'raw_payload' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'sent_at' => 'datetime',
    ];

    public function transportista(): BelongsTo
    {
        return $this->belongsTo(Transportista::class);
    }

    public function transporte(): BelongsTo
    {
        return $this->belongsTo(Transporte::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function stops(): HasMany
    {
        return $this->hasMany(TrafficRouteStop::class)->orderBy('sequence');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(TrafficRouteAssignment::class)->orderBy('assigned_at');
    }

    public function orders()
    {
        return $this->belongsToMany(Order::class, 'traffic_route_order')->withTimestamps();
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    protected static function booted(): void
    {
        static::deleting(function (TrafficRoute $route) {
            TrafficLooseStop::releaseFromRoute($route->id);
        });
    }

    public function getActualDurationSecondsAttribute(): ?int
    {
        if (! $this->started_at) {
            return null;
        }

        $last = $this->stops
            ->filter(function ($stop) {
                return $stop->completed_at !== null;
            })
            ->sortByDesc('completed_at')
            ->first();

        if (! $last) {
            return null;
        }

        return max(0, $last->completed_at->diffInSeconds($this->started_at));
    }

    public function getActualDurationFormattedAttribute(): ?string
    {
        $seconds = $this->actual_duration_seconds;
        if (! $seconds) {
            return null;
        }

        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);

        if ($hours > 0) {
            return sprintf('%dh %02dm', $hours, $minutes);
        }

        return sprintf('%dm', $minutes);
    }

    public function getDelaySecondsAttribute(): ?int
    {
        if (! $this->actual_duration_seconds || ! $this->duration_seconds) {
            return null;
        }

        return max(0, $this->actual_duration_seconds - $this->duration_seconds);
    }

    public function getDelayFormattedAttribute(): ?string
    {
        $seconds = $this->delay_seconds;
        if (! $seconds) {
            return null;
        }

        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);

        if ($hours > 0) {
            return sprintf('%dh %02dm', $hours, $minutes);
        }

        return sprintf('%dm', $minutes);
    }

    public function getDistanceKmAttribute(): ?float
    {
        return $this->distance_meters ? round($this->distance_meters / 1000, 2) : null;
    }

    public function getDurationFormattedAttribute(): ?string
    {
        if (!$this->duration_seconds) {
            return null;
        }

        $hours = intdiv($this->duration_seconds, 3600);
        $minutes = intdiv($this->duration_seconds % 3600, 60);

        if ($hours > 0) {
            return sprintf('%dh %02dm', $hours, $minutes);
        }

        return sprintf('%dm', $minutes);
    }

    public static function generateCode(): string
    {
        return 'TRF-' . now()->format('Ymd') . '-' . Str::upper(Str::random(4));
    }
}
