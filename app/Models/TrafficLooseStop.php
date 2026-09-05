<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class TrafficLooseStop extends Model
{
    use HasFactory;

    protected $fillable = [
        'party_id',
        'uploaded_by',
        'code',
        'address',
        'order_date',
        'sender_name',
        'sender_address',
        'sender_contact',
        'recipient_name',
        'recipient_address',
        'recipient_contact',
        'tracking_number',
        'length',
        'width',
        'height',
        'weight_actual',
        'weight_volumetric',
        'content_description',
        'declared_value',
        'barcode',
        'qr_code',
        'latitude',
        'longitude',
        'priority',
        'notes',
        'source_filename',
        'fingerprint',
        'assigned_route_id',
        'assigned_stop_id',
        'assigned_at',
    ];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'assigned_at' => 'datetime',
        'order_date' => 'datetime',
        'length' => 'float',
        'width' => 'float',
        'height' => 'float',
        'weight_actual' => 'float',
        'weight_volumetric' => 'float',
        'declared_value' => 'float',
    ];

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'party_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function route(): BelongsTo
    {
        return $this->belongsTo(TrafficRoute::class, 'assigned_route_id');
    }

    public function routeStop(): BelongsTo
    {
        return $this->belongsTo(TrafficRouteStop::class, 'assigned_stop_id');
    }

    public function scopePending($query)
    {
        return $query->whereNull('assigned_at');
    }

    public static function releaseFromRoute(int $routeId): void
    {
        if (! $routeId) {
            return;
        }

        static::where('assigned_route_id', $routeId)
            ->update([
                'assigned_route_id' => null,
                'assigned_stop_id' => null,
                'assigned_at' => null,
            ]);
    }

    public static function fingerprint(?int $partyId, ?string $code, string $address): string
    {
        $normalized = Str::lower(Str::of((string) $address)->ascii()->replace('  ', ' ')->trim());
        $codePart = Str::lower(Str::of((string) $code)->ascii()->trim());

        return sha1($partyId . '|' . $codePart . '|' . $normalized);
    }
}
