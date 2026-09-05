<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Allocation extends Model
{
    use HasFactory;

    protected $fillable = [
        'document_id',
        'target_document_id',
        'amount',
        'installment_id',
    ];

    /**
     * Documento que origina la imputación
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'document_id');
    }

    /**
     * Documento al que se imputa (el destino)
     */
    public function target(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'target_document_id');
    }

    /**
     * Alias para compatibilidad con vistas antiguas que esperan "targetDocument"
     */
    public function targetDocument(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'target_document_id');
    }

    /**
     * Cuota (scheduled_installment) a la que se imputó este allocation
     */
    public function installment(): BelongsTo
    {
        return $this->belongsTo(ScheduledInstallment::class, 'installment_id');
    }
}
