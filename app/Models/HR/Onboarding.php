<?php

namespace App\Models\HR;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Onboarding extends Model
{
    protected $table = 'hr_onboardings';

    protected $fillable = [
        'candidate_name',
        'candidate_email',
        'candidate_phone',
        'candidate_dni',
        'invitation_token',
        'invited_at',
        'expires_at',
        'target_hire_date',
        'department_id',
        'position_id',
        'branch_id',
        'manager_id',
        'employee_id',
        'status',
        'progress_percentage',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'invited_at' => 'datetime',
        'expires_at' => 'datetime',
        'target_hire_date' => 'date',
        'progress_percentage' => 'integer',
    ];

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

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(OnboardingTask::class, 'onboarding_id');
    }

    public function recalculateProgress(): void
    {
        $total = $this->tasks()->count();
        if ($total === 0) {
            $this->progress_percentage = 0;
        } else {
            $completed = $this->tasks()->whereIn('status', ['completada', 'aprobada'])->count();
            $this->progress_percentage = (int) round(($completed / $total) * 100);
        }
        $this->save();
    }

    public function getStatusBadgeAttribute(): string
    {
        switch ($this->status) {
            case 'invitacion_pendiente':
                return '<span class="badge bg-secondary">Invitación Pendiente</span>';
            case 'invitado':
                return '<span class="badge bg-info text-dark">Invitado</span>';
            case 'en_proceso':
                return '<span class="badge bg-primary">En Proceso</span>';
            case 'documentacion_pendiente':
                return '<span class="badge bg-warning text-dark">Doc. Pendiente</span>';
            case 'en_revision':
                return '<span class="badge bg-warning text-dark">En Revisión</span>';
            case 'observado':
                return '<span class="badge bg-danger">Observado</span>';
            case 'aprobado':
                return '<span class="badge bg-success">Aprobado</span>';
            case 'completado':
                return '<span class="badge bg-success">Completado</span>';
            case 'cancelado':
                return '<span class="badge bg-dark">Cancelado</span>';
            default:
                return '<span class="badge bg-light text-dark">' . ucfirst($this->status) . '</span>';
        }
    }
}
