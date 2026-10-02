<?php

namespace App\Models\HR;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Employee extends Model
{
    use SoftDeletes;

    protected $table = 'hr_employees';

    protected $fillable = [
        'user_id',
        'file_number',
        'first_name',
        'last_name',
        'dni',
        'cuil',
        'gender',
        'birth_date',
        'nationality',
        'marital_status',
        'phone',
        'personal_email',
        'work_email',
        'address',
        'city',
        'province',
        'postal_code',
        'emergency_contact_name',
        'emergency_contact_phone',
        'emergency_contact_relationship',
        'department_id',
        'position_id',
        'branch_id',
        'manager_id',
        'agreement_id',
        'hire_date',
        'vacation_seniority_date',
        'probation_end_date',
        'contract_type',
        'status',
        'termination_date',
        'termination_reason',
        'salary',
        'avatar_path',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'birth_date' => 'date',
        'hire_date' => 'date',
        'vacation_seniority_date' => 'date',
        'probation_end_date' => 'date',
        'termination_date' => 'date',
        'salary' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class, 'position_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'manager_id');
    }

    public function directReports(): HasMany
    {
        return $this->hasMany(Employee::class, 'manager_id');
    }

    public function agreement(): BelongsTo
    {
        return $this->belongsTo(Agreement::class, 'agreement_id');
    }

    public function leaveBalances(): HasMany
    {
        return $this->hasMany(LeaveBalance::class, 'employee_id');
    }

    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class, 'employee_id');
    }

    public function documentAssignments(): HasMany
    {
        return $this->hasMany(DocumentAssignment::class, 'employee_id');
    }

    public function files(): HasMany
    {
        return $this->hasMany(EmployeeFile::class, 'employee_id');
    }

    public function profileRequests(): HasMany
    {
        return $this->hasMany(ProfileRequest::class, 'employee_id');
    }

    // Accessors & Helpers
    public function getFullNameAttribute(): string
    {
        return "{$this->last_name}, {$this->first_name}";
    }

    public function getEffectiveVacationSeniorityDateAttribute()
    {
        return $this->vacation_seniority_date ?? $this->hire_date;
    }

    public function getSeniorityYearsAttribute(): int
    {
        $startDate = $this->effective_vacation_seniority_date;
        if (!$startDate) return 0;
        $endDate = $this->termination_date ?? Carbon::now();
        return (int) $startDate->diffInYears($endDate);
    }

    public function getSeniorityFormattedAttribute(): string
    {
        $startDate = $this->effective_vacation_seniority_date;
        if (!$startDate) return '-';
        $endDate = $this->termination_date ?? Carbon::now();
        $years = $startDate->diffInYears($endDate);
        $months = $startDate->copy()->addYears($years)->diffInMonths($endDate);
        return "{$years} a, {$months} m";
    }

    public function isActive(): bool
    {
        return $this->status === 'activo';
    }

    public function isExEmployee(): bool
    {
        return $this->status === 'egresado';
    }

    public function getStatusBadgeAttribute(): string
    {
        switch ($this->status) {
            case 'activo':
                return '<span class="badge bg-success">Activo</span>';
            case 'licencia':
                return '<span class="badge bg-warning text-dark">En Licencia</span>';
            case 'suspendido':
                return '<span class="badge bg-danger">Suspendido</span>';
            case 'en_onboarding':
                return '<span class="badge bg-info text-dark">Onboarding</span>';
            case 'egresado':
                return '<span class="badge bg-secondary">Egresado</span>';
            default:
                return '<span class="badge bg-light text-dark">' . ucfirst($this->status) . '</span>';
        }
    }
}
