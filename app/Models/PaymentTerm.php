<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaymentTerm extends Model
{
    protected $fillable = ['name','days'];
    protected $casts = ['days' => 'array'];

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }
}
