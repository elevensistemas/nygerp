<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Supplier extends Model
{
    protected $fillable = [
        'name','tax_id','iva_condition','iibb_number','address','city',
        'province','postal_code','phone','mobile','email',
        'bank_alias','bank_cbu','active'
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    public function scopeActive($q)
    {
        return $q->where('active', true);
    }

    public function taxes(): BelongsToMany
    {
        return $this->belongsToMany(Tax::class)->withTimestamps();
    }
}
