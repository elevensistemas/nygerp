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
        'deducts_from_balance',
        'requires_attachment',
        'requires_approval',
        'allows_half_day',
        'min_advance_days',
        'color',
        'is_active',
    ];

    protected $casts = [
        'deducts_from_balance' => 'boolean',
        'requires_attachment' => 'boolean',
        'requires_approval' => 'boolean',
        'allows_half_day' => 'boolean',
        'is_active' => 'boolean',
        'days_allowed_per_year' => 'decimal:1',
    ];

    public function balances(): HasMany
    {
        return $this->hasMany(LeaveBalance::class, 'leave_type_id');
    }

    public function requests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class, 'leave_type_id');
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
