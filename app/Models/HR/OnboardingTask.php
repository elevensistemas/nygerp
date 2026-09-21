<?php

namespace App\Models\HR;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OnboardingTask extends Model
{
    protected $table = 'hr_onboarding_tasks';

    protected $fillable = [
        'onboarding_id',
        'title',
        'description',
        'task_type',
        'category',
        'is_required',
        'due_date',
        'status',
        'file_path',
        'file_name',
        'form_data',
        'observation_notes',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'due_date' => 'date',
        'form_data' => 'array',
        'reviewed_at' => 'datetime',
    ];

    public function onboarding(): BelongsTo
    {
        return $this->belongsTo(Onboarding::class, 'onboarding_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function getStatusBadgeAttribute(): string
    {
        switch ($this->status) {
            case 'pendiente':
                return '<span class="badge bg-secondary">Pendiente</span>';
            case 'completada':
                return '<span class="badge bg-info text-dark">Cargada</span>';
            case 'en_revision':
                return '<span class="badge bg-warning text-dark">En Revisión</span>';
            case 'observada':
                return '<span class="badge bg-danger">Observada</span>';
            case 'aprobada':
                return '<span class="badge bg-success">Aprobada</span>';
            case 'rechazada':
                return '<span class="badge bg-danger">Rechazada</span>';
            default:
                return '<span class="badge bg-light text-dark">' . ucfirst($this->status) . '</span>';
        }
    }
}
