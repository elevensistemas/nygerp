<?php

namespace App\Models\HR;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
        'start_date',
        'end_date',
        'days_requested',
        'is_half_day',
        'half_day_type',
        'reason',
        'confidential_notes',
        'notes',
        'attachment_path',
        'attachment_original_name',
        'attachment_name',
        'attachment_mime',
        'status',
        'idempotency_token',
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

    public function getStartDateAttribute()
    {
        return $this->attributes['date_from'] ?? null;
    }

    public function setStartDateAttribute($value)
    {
        $this->attributes['date_from'] = $value;
    }

    public function getEndDateAttribute()
    {
        return $this->attributes['date_to'] ?? null;
    }

    public function setEndDateAttribute($value)
    {
        $this->attributes['date_to'] = $value;
    }

    public function getDaysRequestedAttribute()
    {
        return $this->attributes['days_count'] ?? 0;
    }

    public function setDaysRequestedAttribute($value)
    {
        $this->attributes['days_count'] = $value;
    }

    public function getAttachmentNameAttribute()
    {
        return $this->attachment_original_name ?? ($this->attachment_path ? basename($this->attachment_path) : null);
    }

    public function setAttachmentNameAttribute($value)
    {
        $this->attributes['attachment_original_name'] = $value;
    }

    public function getAttachmentMimeAttribute()
    {
        return 'application/pdf';
    }

    public function setAttachmentMimeAttribute($value)
    {
        // Virtual attribute
    }

    public function setStatusAttribute($value)
    {
        $map = [
            'pending' => 'pendiente_rrhh', // Default legacy pending maps to rrhh
            'approved' => 'aprobada',
            'rejected' => 'rechazada',
            'cancelled' => 'cancelada',
            'draft' => 'borrador',
            'completed' => 'finalizada',
            'pendiente' => 'pendiente_rrhh',
        ];
        $this->attributes['status'] = $map[$value] ?? $value;
    }

    public function isApproved(): bool
    {
        return in_array($this->status, ['aprobada', 'approved']);
    }

    public function isPending(): bool
    {
        return in_array($this->status, ['pendiente_manager', 'pendiente_rrhh', 'pendiente', 'pending']);
    }

    public function isRejected(): bool
    {
        return in_array($this->status, ['rechazada', 'rejected']);
    }

    public function isCancelled(): bool
    {
        return in_array($this->status, ['cancelada', 'cancelled']);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class, 'leave_type_id');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(LeaveRequestAllocation::class, 'leave_request_id');
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

    public function approvalLogs(): HasMany
    {
        return $this->hasMany(LeaveApprovalLog::class, 'leave_request_id')->latest();
    }

    public function getStatusBadgeAttribute(): string
    {
        switch ($this->status) {
            case 'borrador':
                return '<span class="badge bg-secondary">Borrador</span>';
            case 'pendiente_manager':
                return '<span class="badge bg-warning text-dark"><i class="fa-solid fa-user-clock me-1"></i>Pendiente Manager</span>';
            case 'pendiente_rrhh':
            case 'pendiente':
                return '<span class="badge bg-info text-dark"><i class="fa-solid fa-clock me-1"></i>Pendiente RR. HH.</span>';
            case 'aprobada':
                return '<span class="badge bg-success"><i class="fa-solid fa-check me-1"></i>Aprobada</span>';
            case 'rechazada':
                return '<span class="badge bg-danger"><i class="fa-solid fa-xmark me-1"></i>Rechazada</span>';
            case 'cancelada':
                return '<span class="badge bg-dark">Cancelada</span>';
            case 'finalizada':
                return '<span class="badge bg-secondary text-light">Finalizada</span>';
            default:
                return '<span class="badge bg-light text-dark">' . ucfirst($this->status) . '</span>';
        }
    }
}
