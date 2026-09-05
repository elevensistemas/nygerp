<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DriverPaymentConcept extends Model
{
    use HasFactory;

    public const VALUE_FIXED = 'fixed';
    public const VALUE_REFERENCE = 'reference';

    protected $fillable = [
        'name',
        'value_type',
        'default_amount',
        'sign',
        'reference_concept_id',
        'reference_multiplier',
        'active',
    ];

    protected $casts = [
        'default_amount' => 'decimal:2',
        'sign' => 'integer',
        'reference_multiplier' => 'decimal:4',
        'active' => 'boolean',
    ];

    public function signedAmount(?float $amount): float
    {
        $value = (float) ($amount ?? 0);

        return (int) $this->sign === -1 ? ($value * -1) : $value;
    }

    public function zoneConcepts(): HasMany
    {
        return $this->hasMany(DriverPaymentZoneConcept::class, 'driver_payment_concept_id');
    }

    public function referenceConcept()
    {
        return $this->belongsTo(self::class, 'reference_concept_id');
    }
}
