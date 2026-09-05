<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DriverPaymentFleet extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'billing_transportista_id',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    public function billingTransportista(): BelongsTo
    {
        return $this->belongsTo(Transportista::class, 'billing_transportista_id');
    }

    public function transportistas(): BelongsToMany
    {
        return $this->belongsToMany(Transportista::class, 'driver_payment_fleet_transportista')
            ->withTimestamps();
    }

    public function recibos(): HasMany
    {
        return $this->hasMany(ReciboChofer::class, 'driver_payment_fleet_id');
    }
}
