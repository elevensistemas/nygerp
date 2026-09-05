<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DriverLiquidationSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'tipo_periodo',
        'quincena',
        'desde',
        'hasta',
        'activo',
    ];

    protected $casts = [
        'quincena' => 'integer',
        'desde' => 'integer',
        'hasta' => 'integer',
        'activo' => 'boolean',
    ];
}
