<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReciboChoferItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'recibo_chofer_id',
        'concepto',
        'cantidad',
        'zona',
        'paradas',
        'paquetes',
        'entregados',
        'importe_unitario',
        'importe',
        'source_key',
        'meta',
        'driver_status',
        'driver_comment',
        'driver_status_date',
    ];

    protected $casts = [
        'cantidad' => 'decimal:3',
        'paradas' => 'decimal:3',
        'paquetes' => 'decimal:3',
        'entregados' => 'decimal:3',
        'importe_unitario' => 'decimal:2',
        'importe' => 'decimal:2',
        'meta' => 'array',
        'driver_status_date' => 'datetime',
    ];

    public function recibo(): BelongsTo
    {
        return $this->belongsTo(ReciboChofer::class, 'recibo_chofer_id');
    }

    public function logs()
    {
        return $this->hasMany(DriverPaymentItemLog::class, 'recibo_chofer_item_id');
    }
}
