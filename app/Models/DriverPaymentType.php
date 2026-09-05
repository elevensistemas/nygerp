<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DriverPaymentType extends Model
{
    use HasFactory;

    protected $fillable = [
        'description',
        'type',
    ];

    protected $casts = [
        'type' => 'boolean',
    ];

    public function planillaLinks(): HasMany
    {
        return $this->hasMany(PlanillaPagoChoferRecibo::class, 'driver_payment_type_id');
    }
}
