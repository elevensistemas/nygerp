<?php

namespace App\Models\HR;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Agreement extends Model
{
    protected $table = 'hr_agreements';

    protected $fillable = [
        'name',
        'code',
        'description',
        'vacation_scale',
        'is_active',
    ];

    protected $casts = [
        'vacation_scale' => 'array',
        'is_active' => 'boolean',
    ];

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class, 'agreement_id');
    }

    /**
     * Calcula los días de vacaciones correspondientes según años de antigüedad
     */
    public function getVacationDaysForSeniority(int $years): int
    {
        if (empty($this->vacation_scale)) {
            // Escala por defecto Ley de Contrato de Trabajo (Argentina)
            if ($years < 5) return 14;
            if ($years < 10) return 21;
            if ($years < 20) return 28;
            return 35;
        }

        foreach ($this->vacation_scale as $scale) {
            $min = $scale['min_years'] ?? 0;
            $max = $scale['max_years'] ?? 999;
            if ($years >= $min && $years <= $max) {
                return (int) ($scale['days'] ?? 14);
            }
        }

        return 14;
    }
}
