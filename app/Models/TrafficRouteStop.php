<?php

namespace App\Models;

use App\Models\TrafficLooseStop;
use App\Models\TrafficRouteStopPhoto;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TrafficRouteStop extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_ENTREGADO = 'entregado';
    public const STATUS_NO_ENTREGADO = 'no_entregado';

    protected $fillable = [
        'traffic_route_id',
        'order_id',
        'traffic_loose_stop_id',
        'sequence',
        'is_extra',
        'label',
        'address',
        'latitude',
        'longitude',
        'city',
        'postal_code',
        'contact_name',
        'contact_phone',
        'notes',
        'status',
        'delivery_reason_id',
        'recipient_dni',
        'recipient_name',
        'recipient_is_owner',
        'status_notes',
        'attempted_at',
        'completed_at',
    ];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'is_extra' => 'bool',
        'recipient_is_owner' => 'bool',
        'attempted_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function route(): BelongsTo
    {
        return $this->belongsTo(TrafficRoute::class, 'traffic_route_id');
    }

    public function deliveryReason(): BelongsTo
    {
        return $this->belongsTo(DeliveryReason::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(TrafficRouteStopEvent::class)->orderBy('happened_at');
    }

    public function looseStop(): BelongsTo
    {
        return $this->belongsTo(TrafficLooseStop::class, 'traffic_loose_stop_id');
    }

    public function photos(): HasMany
    {
        return $this->hasMany(TrafficRouteStopPhoto::class)->orderByDesc('created_at');
    }

    public static function statusOptions(): array
    {
        return [
            self::STATUS_PENDING => 'Pendiente',
            self::STATUS_ENTREGADO => 'Entregado',
            self::STATUS_NO_ENTREGADO => 'No entregado',
        ];
    }

    public static function requiresRecipientDni(string $status, ?bool $recipientIsOwner = null): bool
    {
        return $status === self::STATUS_ENTREGADO && $recipientIsOwner === false;
    }

    public static function requiresStatusNotes(string $status): bool
    {
        return false;
    }
}
