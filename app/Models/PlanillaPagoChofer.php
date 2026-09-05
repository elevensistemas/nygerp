<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PlanillaPagoChofer extends Model
{
    use HasFactory;
    use SoftDeletes;

    public const ESTADO_BORRADOR = 'borrador';
    public const ESTADO_CONFIRMADA = 'confirmada';
    public const ESTADO_PAGADA_PARCIAL = 'pagada_parcial';
    public const ESTADO_CERRADA = 'cerrada';

    protected $table = 'planillas_pago_chofer';

    protected $fillable = [
        'numero',
        'fecha',
        'estado',
        'total',
        'observaciones',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'fecha' => 'date',
        'total' => 'decimal:2',
    ];

    public function reciboLinks(): HasMany
    {
        return $this->hasMany(PlanillaPagoChoferRecibo::class, 'planilla_pago_chofer_id');
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
        $this->total = (float) $this->reciboLinks()->sum('monto_en_planilla');
        $this->save();
    }

    public function isDeletableUnprocessed(): bool
    {
        if (! in_array($this->estado, [self::ESTADO_BORRADOR, self::ESTADO_CONFIRMADA], true)) {
            return false;
        }

        return ! $this->reciboLinks()
            ->where('estado_pago', '!=', PlanillaPagoChoferRecibo::ESTADO_SIN_RESULTADO)
            ->exists();
    }
}
