<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransportistaLiquidationMeta extends Model
{
    use HasFactory;

    protected $table = 'transportista_liquidation_meta';

    protected $fillable = [
        'transportista_id',
        'titular',
        'patente',
        'modelo',
        'unidad',
        'plaza',
        'tipo_periodo',
        'extra',
    ];

    protected $casts = [
        'extra' => 'array',
    ];

    public function transportista(): BelongsTo
    {
        return $this->belongsTo(Transportista::class);
    }
}
