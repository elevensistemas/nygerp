<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

class Document extends Model
{
    public const DOCTYPE_LABELS = [
        'invoice'       => 'Factura',
        'credit_note'   => 'Nota de Crédito',
        'debit_note'    => 'Nota de Débito',
        'receipt'       => 'Recibo',
        'payment_order' => 'Orden de Pago',
        'fund_movement' => 'Movimiento de Fondo',
    ];

    protected $fillable = [
        'scope',
        'doctype',
        'number',
        'supplier_id',
        'issue_date',
        'payment_term_id',
        'subtotal',
        'tax',
        'total',
        'affects_ledger',
        'affects_current_account',
        'notes',
    ];

    protected $casts = [
        'issue_date' => 'date',
        'affects_ledger' => 'bool',
        'affects_current_account' => 'bool',
    ];

    protected $attributes = [
        'affects_ledger' => true,
        'affects_current_account' => true,
    ];

    public static function labelForDoctype(?string $doctype): string
    {
        $key = strtolower((string) $doctype);

        return self::DOCTYPE_LABELS[$key] ?? ucfirst(str_replace('_', ' ', $key));
    }

    public function getDoctypeLabelAttribute(): string
    {
        return self::labelForDoctype($this->doctype);
    }

    // --- Relaciones principales ---
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function term(): BelongsTo
    {
        return $this->belongsTo(PaymentTerm::class, 'payment_term_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(DocumentLine::class)->with(['product','account','costCenter']);
    }

    public function installments(): HasMany
    {
        return $this->hasMany(ScheduledInstallment::class);
    }

    // --- Las que faltaban ---
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(Allocation::class);
    }

    /**
     * Imputaciones recibidas por este comprobante (origen = otra factura/OP que imputa a este documento)
     */
    public function allocationsReceived(): HasMany
    {
        return $this->hasMany(Allocation::class, 'target_document_id');
    }
}
