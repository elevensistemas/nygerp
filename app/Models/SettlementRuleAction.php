<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class SettlementRuleAction extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'settlement_rule_id',
        'action_type',
        'payload',
    ];

    protected $casts = [
        'payload' => 'json',
    ];

    public function rule(): BelongsTo
    {
        return $this->belongsTo(SettlementRule::class, 'settlement_rule_id');
    }
}
