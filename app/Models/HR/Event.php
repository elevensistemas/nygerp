<?php

namespace App\Models\HR;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Event extends Model
{
    protected $table = 'hr_events';

    protected $fillable = [
        'title',
        'description',
        'event_type',
        'start_date',
        'end_date',
        'is_all_day',
        'color',
        'location',
        'department_id',
        'branch_id',
        'employee_id',
        'created_by',
    ];

    protected $casts = [
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'is_all_day' => 'boolean',
    ];

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getTypeBadgeAttribute(): string
    {
        switch ($this->event_type) {
            case 'cumpleanos':
                return '<span class="badge bg-danger"><i class="fa-solid fa-cake-candles me-1"></i>Cumpleaños</span>';
            case 'aniversario':
                return '<span class="badge bg-warning text-dark"><i class="fa-solid fa-award me-1"></i>Aniversario</span>';
            case 'feriado':
                return '<span class="badge bg-info text-dark"><i class="fa-solid fa-calendar-day me-1"></i>Feriado</span>';
            case 'capacitacion':
                return '<span class="badge bg-primary"><i class="fa-solid fa-graduation-cap me-1"></i>Capacitación</span>';
            default:
                return '<span class="badge bg-secondary"><i class="fa-solid fa-calendar me-1"></i>' . ucfirst($this->event_type) . '</span>';
        }
    }
}
