<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\User;

class SystemParameterLog extends Model
{
    protected $fillable = [
        'system_parameter_id',
        'user_id',
        'old_value',
        'new_value',
    ];

    public function parameter(): BelongsTo
    {
        return $this->belongsTo(SystemParameter::class, 'system_parameter_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
