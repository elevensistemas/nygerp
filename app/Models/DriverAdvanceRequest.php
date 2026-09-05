<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DriverAdvanceRequest extends Model
{
    use HasFactory;

    public const ESTADO_PENDIENTE = 'pendiente';
    public const ESTADO_APROBADO = 'aprobado';
    public const ESTADO_RECHAZADO = 'rechazado';
    public const ESTADO_CONTRAOFERTADO = 'contraofertado';

    protected $table = 'driver_advance_requests';

    protected $fillable = [
        'transportista_id',
        'monto_pedido',
        'comentario_chofer',
        'fecha_pedido',
        'estado',
        'monto_aprobado',
        'fecha_resolucion',
        'observaciones_admin',
        'resolved_by',
        'recibo_chofer_id',
    ];

    protected $casts = [
        'monto_pedido' => 'decimal:2',
        'monto_aprobado' => 'decimal:2',
        'fecha_pedido' => 'date',
        'fecha_resolucion' => 'datetime',
    ];

    public function transportista(): BelongsTo
    {
        return $this->belongsTo(Transportista::class);
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function reciboChofer(): BelongsTo
    {
        return $this->belongsTo(ReciboChofer::class, 'recibo_chofer_id');
    }
}
