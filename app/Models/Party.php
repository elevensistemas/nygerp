<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use App\Models\TrafficLooseStopImportFormat;

class Party extends Model
{
    protected $fillable = [
        'role',
        'name',
        'business_name',
        'tax_id',
        'iva_condition',
        'iibb_number',
        'email',
        'email_secondary',
        'phone',
        'mobile',
        'address',
        'city',
        'province',
        'postal_code',
        'bank_alias',
        'bank_cbu',
        'notes',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    public function looseStopImportFormat(): HasOne
    {
        return $this->hasOne(TrafficLooseStopImportFormat::class);
    }
}
