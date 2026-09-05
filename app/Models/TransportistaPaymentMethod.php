<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransportistaPaymentMethod extends Model
{
    use HasFactory;

    protected $fillable = [
        'transportista_id',
        'bank_id',
        'cbu',
        'account_number',
        'description',
        'is_default',
        'tags',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'tags' => 'array',
    ];

    public function transportista(): BelongsTo
    {
        return $this->belongsTo(Transportista::class);
    }

    public function bank(): BelongsTo
    {
        return $this->belongsTo(Bank::class);
    }
}
