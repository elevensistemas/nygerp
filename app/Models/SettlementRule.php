<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletes;

class SettlementRule extends Model
{
    use HasFactory, SoftDeletes;

    public const SCOPE_LINE = 'line';
    public const SCOPE_RECEIPT = 'receipt';

    protected $fillable = [
        'name',
        'priority',
        'is_modifier',
        'active',
        'scope',
    ];

    protected $casts = [
        'priority' => 'integer',
        'is_modifier' => 'boolean',
        'active' => 'boolean',
    ];

    protected $attributes = [
        'scope' => self::SCOPE_LINE,
    ];

    /**
     * Scope a query to only include active rules.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    public function conditions(): HasMany
    {
        return $this->hasMany(SettlementRuleCondition::class, 'settlement_rule_id');
    }

    public function action(): HasOne
    {
        return $this->hasOne(SettlementRuleAction::class, 'settlement_rule_id');
    }
}
