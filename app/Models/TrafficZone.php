<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class TrafficZone extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'type',
        'center_lat',
        'center_lng',
        'radius_km',
        'polygon',
        'priority',
        'is_soft',
        'max_stops',
        'uses_model_year_values',
        'svs_values',
    ];

    protected $casts = [
        'center_lat' => 'float',
        'center_lng' => 'float',
        'radius_km' => 'float',
        'polygon' => 'array',
        'is_soft' => 'boolean',
        'max_stops' => 'integer',
        'uses_model_year_values' => 'boolean',
        'svs_values' => 'array',
    ];

    public function transportistas(): BelongsToMany
    {
        return $this->belongsToMany(Transportista::class, 'traffic_zone_transportista')->withTimestamps();
    }

    public function containsPoint(float $lat, float $lng): bool
    {
        if ($this->type === 'circle') {
            if ($this->radius_km === null || $this->center_lat === null || $this->center_lng === null) {
                return false;
            }

            return $this->haversine($lat, $lng, $this->center_lat, $this->center_lng) <= (float) $this->radius_km;
        }

        if ($this->type === 'polygon' && is_array($this->polygon) && count($this->polygon) >= 3) {
            return $this->pointInPolygon($lat, $lng, $this->polygon);
        }

        return false;
    }

    public function distanceToCenter(float $lat, float $lng): ?float
    {
        if ($this->center_lat === null || $this->center_lng === null) {
            return null;
        }

        return $this->haversine($lat, $lng, $this->center_lat, $this->center_lng);
    }

    private function pointInPolygon(float $lat, float $lng, array $polygon): bool
    {
        $inside = false;
        $points = array_values($polygon);
        $count = count($points);

        for ($i = 0, $j = $count - 1; $i < $count; $j = $i++) {
            $pi = $points[$i];
            $pj = $points[$j];
            $latI = isset($pi['lat']) ? (float) $pi['lat'] : (float) ($pi[0] ?? 0);
            $lngI = isset($pi['lng']) ? (float) $pi['lng'] : (float) ($pi[1] ?? 0);
            $latJ = isset($pj['lat']) ? (float) $pj['lat'] : (float) ($pj[0] ?? 0);
            $lngJ = isset($pj['lng']) ? (float) $pj['lng'] : (float) ($pj[1] ?? 0);

            $intersects = (($lngI > $lng) !== ($lngJ > $lng)) &&
                ($lat < ($latJ - $latI) * ($lng - $lngI) / (($lngJ - $lngI) ?: 1e-9) + $latI);

            if ($intersects) {
                $inside = !$inside;
            }
        }

        return $inside;
    }

    private function haversine(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371; // km
        $latFrom = deg2rad($lat1);
        $lonFrom = deg2rad($lon1);
        $latTo = deg2rad($lat2);
        $lonTo = deg2rad($lon2);

        $latDelta = $latTo - $latFrom;
        $lonDelta = $lonTo - $lonFrom;

        $angle = 2 * asin(sqrt(pow(sin($latDelta / 2), 2) +
            cos($latFrom) * cos($latTo) * pow(sin($lonDelta / 2), 2)));
        return $earthRadius * $angle;
    }
}
