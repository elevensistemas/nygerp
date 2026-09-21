<?php

namespace App\Models\HR;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentAssignment extends Model
{
    protected $table = 'hr_document_assignments';

    protected $fillable = [
        'document_id',
        'employee_id',
        'user_id',
        'status',
        'viewed_at',
        'signed_at',
        'response_type',
        'comments',
        'ip_address',
        'user_agent',
        'signed_file_hash',
        'allow_ex_employee',
    ];

    protected $casts = [
        'viewed_at' => 'datetime',
        'signed_at' => 'datetime',
        'allow_ex_employee' => 'boolean',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'document_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function getStatusBadgeAttribute(): string
    {
        switch ($this->status) {
            case 'pendiente_lectura':
                return '<span class="badge bg-warning text-dark"><i class="fa-solid fa-eye me-1"></i>Pendiente Lectura</span>';
            case 'pendiente_firma':
                return '<span class="badge bg-warning text-dark"><i class="fa-solid fa-signature me-1"></i>Pendiente Firma</span>';
            case 'firmado_conforme':
                return '<span class="badge bg-success"><i class="fa-solid fa-check-double me-1"></i>Firmado Conforme</span>';
            case 'firmado_disconforme':
                return '<span class="badge bg-danger"><i class="fa-solid fa-circle-exclamation me-1"></i>Firmado Disconforme</span>';
            case 'vencido':
                return '<span class="badge bg-dark">Vencido</span>';
            case 'anulado':
                return '<span class="badge bg-secondary">Anulado</span>';
            default:
                return '<span class="badge bg-light text-dark">' . ucfirst($this->status) . '</span>';
        }
    }
}
