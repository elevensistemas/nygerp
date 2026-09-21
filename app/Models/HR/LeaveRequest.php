<?php

namespace App\Models\HR;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class LeaveRequest extends Model
{
    use SoftDeletes;

    protected $table = 'hr_leave_requests';

    protected $fillable = [
        'employee_id',
        'leave_type_id',
        'date_from',
        'date_to',
        'days_count',
        'is_half_day',
        'half_day_type',
        'reason',
        'notes',
        'attachment_path',
        'status',
        'current_approver_id',
        'approved_by',
        'approved_at',
        'rejected_by',
        'rejected_at',
        'rejection_reason',
    ];

    protected $casts = [
        'date_from' => 'date',
        'date_to' => 'date',
        'days_count' => 'decimal:1',
        'is_half_day' => 'boolean',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class, 'leave_type_id');
    }

    public function currentApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'current_approver_id');
    }

    public function approvedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function rejectedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function getStatusBadgeAttribute(): string
    {
        switch ($this->status) {
            case 'borrador':
                return '<span class="badge bg-secondary">Borrador</span>';
            case 'pendiente':
                return '<span class="badge bg-warning text-dark"><i class="fa-solid fa-clock me-1"></i>Pendiente</span>';
            case 'aprobada':
                return '<span class="badge bg-success"><i class="fa-solid fa-check me-1"></i>Aprobada</span>';
            case 'rechazada':
                return '<span class="badge bg-danger"><i class="fa-solid fa-xmark me-1"></i>Rechazada</span>';
            case 'cancelada':
                return '<span class="badge bg-dark">Cancelada</span>';
            case 'finalizada':
                return '<span class="badge bg-info text-dark">Finalizada</span>';
            default:
                return '<span class="badge bg-light text-dark">' . ucfirst($this->status) . '</span>';
        }
    }
}
