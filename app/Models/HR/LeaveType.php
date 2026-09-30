<?php

namespace App\Models\HR;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeaveType extends Model
{
    protected $table = 'hr_leave_types';

    protected $fillable = [
        'name',
        'code',
        'description',
        'category',
        'days_allowed_per_year',
        'calculation_unit',
        'counts_as_working_days',
        'deducts_from_balance',
        'requires_attachment',
        'requires_approval',
        'allows_half_day',
        'allows_negative_balance',
        'requires_reason',
        'display_order',
        'max_days_limit',
        'min_advance_days',
        'min_anticipation_days',
        'color',
        'is_active',
    ];

    public function getCountsAsWorkingDaysAttribute(): bool
    {
        return ($this->attributes['calculation_unit'] ?? 'habiles') === 'habiles';
    }

    public function setCountsAsWorkingDaysAttribute($value)
    {
        $this->attributes['calculation_unit'] = ($value && $value != '0' && $value !== 0) ? 'habiles' : 'corridos';
    }

    public function getMinAnticipationDaysAttribute(): int
    {
        return (int) ($this->attributes['min_advance_days'] ?? 0);
    }

    public function setMinAnticipationDaysAttribute($value)
    {
        $this->attributes['min_advance_days'] = $value;
    }

    protected $casts = [
        'deducts_from_balance' => 'boolean',
        'requires_attachment' => 'boolean',
        'requires_approval' => 'boolean',
        'allows_half_day' => 'boolean',
        'allows_negative_balance' => 'boolean',
        'requires_reason' => 'boolean',
        'display_order' => 'integer',
        'is_active' => 'boolean',
        'days_allowed_per_year' => 'decimal:1',
        'max_days_limit' => 'decimal:1',
    ];

    public function balances(): HasMany
    {
        return $this->hasMany(LeaveBalance::class, 'leave_type_id');
    }

    public function requests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class, 'leave_type_id');
    }

    public function policies(): HasMany
    {
        return $this->hasMany(LeavePolicy::class, 'leave_type_id');
    }

    public function getCategoryLabelAttribute(): string
    {
        $labels = [
            'vacaciones' => 'Vacaciones',
            'medica' => 'Licencia Médica',
            'examen_estudio' => 'Examen / Estudio',
            'maternidad_paternidad' => 'Maternidad / Paternidad',
            'accidente' => 'Accidente Laboral',
            'personal_con_goce' => 'Día Personal con goce',
            'personal_sin_goce' => 'Día Personal sin goce',
            'duelo' => 'Duelo',
            'matrimonio' => 'Matrimonio',
            'otro' => 'Otro',
        ];
        return $labels[$this->category] ?? ucfirst($this->category);
    }
}

