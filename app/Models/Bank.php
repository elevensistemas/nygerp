<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Bank extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function transportistas(): HasMany
    {
        return $this->hasMany(Transportista::class);
    }

    public function transportistaPaymentMethods(): HasMany
    {
        return $this->hasMany(TransportistaPaymentMethod::class);
    }
}
