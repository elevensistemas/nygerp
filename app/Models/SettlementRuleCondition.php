<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class SettlementRuleCondition extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'settlement_rule_id',
        'field',
        'operator',
        'value',
    ];

    protected $casts = [
        'value' => 'json',
    ];

    public function rule(): BelongsTo
    {
        return $this->belongsTo(SettlementRule::class, 'settlement_rule_id');
    }
}
