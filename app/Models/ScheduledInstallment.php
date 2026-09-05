<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ScheduledInstallment extends Model
{
    use HasFactory;

    protected $fillable = [
        'document_id',
        'installment_number',
        'due_date',
        'amount',
        'paid',
        'paid_at',
    ];

    protected $casts = [
        'due_date' => 'date',
        'paid'     => 'boolean',
        'paid_at'  => 'datetime',
        'amount'   => 'decimal:2',
    ];

    public function document()
    {
        return $this->belongsTo(Document::class);
    }
}
