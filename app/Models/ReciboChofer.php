<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ReciboChofer extends Model
{
    use HasFactory;
    use SoftDeletes;

    public const ESTADO_CARGADO = 'cargado';
    public const ESTADO_EN_PLANILLA = 'en_planilla';
    public const ESTADO_PAGADO = 'pagado';
    public const ESTADO_PENDIENTE_PAGO = 'pendiente_pago';
    public const ESTADO_ANULADO = 'anulado';

    public const TIPO_QUINCENAL = 'quincenal';
    public const TIPO_MENSUAL = 'mensual';

    protected $table = 'recibos_chofer';

    protected $fillable = [
        'transportista_id',
        'driver_payment_fleet_id',
        'tipo_periodo',
        'periodo_desde',
        'periodo_hasta',
        'fecha_emision',
        'plaza',
        'estado',
        'importe_total',
        'factura_id',
        'factura_ref',
        'factura_pdf_path',
        'factura_pdf_nombre',
        'facturas_adjuntas',
        'factura_fecha',
        'factura_observaciones',
        'origen',
        'source_file',
        'source_sheet',
        'source_key',
        'observaciones',
        'driver_contact_request_comment',
        'driver_contact_request_date',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'periodo_desde' => 'date',
        'periodo_hasta' => 'date',
        'fecha_emision' => 'date',
        'factura_fecha' => 'date',
        'driver_contact_request_date' => 'datetime',
        'importe_total' => 'decimal:2',
        'facturas_adjuntas' => 'array',
    ];

    protected $attributes = [
        'estado' => self::ESTADO_CARGADO,
        'origen' => 'excel_trafico',
        'importe_total' => 0,
    ];

    public function transportista(): BelongsTo
    {
        return $this->belongsTo(Transportista::class);
    }

    public function paymentFleet(): BelongsTo
    {
        return $this->belongsTo(DriverPaymentFleet::class, 'driver_payment_fleet_id');
    }

    public function displayName(): string
    {
        if ($this->relationLoaded('paymentFleet') && $this->paymentFleet) {
            return (string) $this->paymentFleet->name;
        }

        return (string) optional($this->transportista)->name;
    }

    public function items(): HasMany
    {
        return $this->hasMany(ReciboChoferItem::class);
    }

    public function planillaLinks(): HasMany
    {
        return $this->hasMany(PlanillaPagoChoferRecibo::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function recalculateTotal(): void
    {
        $this->importe_total = (float) $this->items()->sum('importe');
        $this->save();
    }

    public function advanceRequests(): HasMany
    {
        return $this->hasMany(DriverAdvanceRequest::class, 'recibo_chofer_id');
    }

    protected static function booted(): void
    {
        static::deleting(function (ReciboChofer $recibo) {
            \DB::table('driver_advance_requests')
                ->where('recibo_chofer_id', $recibo->id)
                ->update(['recibo_chofer_id' => null]);
        });
    }
}
