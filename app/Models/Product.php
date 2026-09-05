<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    protected $fillable = [
        'code',
        'name',
        'description',
        'type',
        'unit',
        'default_price',
        'iva_rate',
        'default_account_id',
        'default_cost_center_id',
        'is_active',
    ];

    protected $casts = [
        'default_price' => 'float',
        'iva_rate' => 'float',
        'is_active' => 'boolean',
    ];

    public function defaultAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'default_account_id');
    }

    public function defaultCostCenter(): BelongsTo
    {
        return $this->belongsTo(CostCenter::class, 'default_cost_center_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(DocumentLine::class);
    }
}
