<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlanillaPagoChoferRecibo extends Model
{
    use HasFactory;

    public const ESTADO_PAGADO = 'pagado';
    public const ESTADO_PENDIENTE = 'pendiente';
    public const ESTADO_SIN_RESULTADO = 'sin_resultado';

    protected $table = 'planilla_pago_chofer_recibos';

    protected $fillable = [
        'planilla_pago_chofer_id',
        'recibo_chofer_id',
        'monto_en_planilla',
        'estado_pago',
        'driver_payment_type_id',
    ];

    protected $casts = [
        'monto_en_planilla' => 'decimal:2',
    ];

    public function planilla(): BelongsTo
    {
        return $this->belongsTo(PlanillaPagoChofer::class, 'planilla_pago_chofer_id');
    }

    public function recibo(): BelongsTo
    {
        return $this->belongsTo(ReciboChofer::class, 'recibo_chofer_id');
    }

    public function paymentType(): BelongsTo
    {
        return $this->belongsTo(DriverPaymentType::class, 'driver_payment_type_id');
    }
}
