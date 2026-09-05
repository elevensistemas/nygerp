<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Party;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_number',
        'party_id',
        'client_name',
        'client_company',
        'client_contact',
        'client_phone',
        'client_email',
        'order_date',
        'status',
        'delivery_instructions',
    ];

    protected $casts = [
        'order_date' => 'date',
    ];

    public function addresses(): HasMany
    {
        return $this->hasMany(OrderAddress::class)->orderBy('sequence');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'party_id');
    }

    public function getClientNameAttribute(?string $value): ?string
    {
        if ($value) {
            return $value;
        }

        $party = $this->client;
        return $party ? ($party->business_name ?: $party->name) : null;
    }

    public function routes(): HasMany
    {
        return $this->hasMany(TrafficRoute::class);
    }
}
