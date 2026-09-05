<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserConfirmation extends Model
{
    protected $fillable = [
        'user_id',
        'token',
        'confirmed_at',
        'ip_address',
        'user_agent',
        'properties',
    ];

    protected $casts = [
        'confirmed_at' => 'datetime',
        'properties' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
