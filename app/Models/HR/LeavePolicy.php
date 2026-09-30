<?php

namespace App\Models\HR;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeavePolicy extends Model
{
    protected $table = 'hr_leave_policies';

    protected $fillable = [
        'leave_type_id',
        'agreement_id',
        'name',
        'seniority_years_from',
        'seniority_years_to',
        'min_seniority_years',
        'max_seniority_years',
        'days_granted',
        'allow_transfer',
        'allows_carryover',
        'max_transferred_days',
        'max_carryover_days',
        'transfer_expiration_months',
        'expiration_months',
        'is_active',
    ];

    protected $casts = [
        'days_granted' => 'decimal:1',
        'max_transferred_days' => 'decimal:1',
        'allow_transfer' => 'boolean',
        'is_active' => 'boolean',
        'seniority_years_from' => 'integer',
        'seniority_years_to' => 'integer',
        'transfer_expiration_months' => 'integer',
    ];

    public function getMinSeniorityYearsAttribute()
    {
        return $this->attributes['seniority_years_from'] ?? 0;
    }

    public function setMinSeniorityYearsAttribute($value)
    {
        $this->attributes['seniority_years_from'] = $value;
    }

    public function getMaxSeniorityYearsAttribute()
    {
        return $this->attributes['seniority_years_to'] ?? null;
    }

    public function setMaxSeniorityYearsAttribute($value)
    {
        $this->attributes['seniority_years_to'] = $value;
    }

    public function getAllowsCarryoverAttribute()
    {
        return $this->attributes['allow_transfer'] ?? true;
    }

    public function setAllowsCarryoverAttribute($value)
    {
        $this->attributes['allow_transfer'] = $value;
    }

    public function getMaxCarryoverDaysAttribute()
    {
        return $this->attributes['max_transferred_days'] ?? null;
    }

    public function setMaxCarryoverDaysAttribute($value)
    {
        $this->attributes['max_transferred_days'] = $value;
    }

    public function getExpirationMonthsAttribute()
    {
        return $this->attributes['transfer_expiration_months'] ?? 6;
    }

    public function setExpirationMonthsAttribute($value)
    {
        $this->attributes['transfer_expiration_months'] = $value;
    }

    public function getCountsAsWorkingDaysAttribute()
    {
        return $this->leaveType ? $this->leaveType->counts_as_working_days : false;
    }

    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class, 'leave_type_id');
    }

    public function agreement(): BelongsTo
    {
        return $this->belongsTo(Agreement::class, 'agreement_id');
    }

    public function balances(): HasMany
    {
        return $this->hasMany(LeaveBalance::class, 'leave_policy_id');
    }

    /**
     * Verifica si existe otra política activa con rangos de antigüedad superpuestos.
     */
    public static function hasOverlappingPolicy(int $leaveTypeId, ?int $agreementId, int $from, ?int $to, ?int $ignoreId = null): bool
    {
        $query = self::where('leave_type_id', $leaveTypeId)
            ->where('is_active', true)
            ->where(function ($q) use ($agreementId) {
                if ($agreementId) {
                    $q->where('agreement_id', $agreementId);
                } else {
                    $q->whereNull('agreement_id');
                }
            });

        if ($ignoreId) {
            $query->where('id', '!=', $ignoreId);
        }

        $policies = $query->get();

        foreach ($policies as $policy) {
            $pFrom = (int) $policy->seniority_years_from;
            $pTo = $policy->seniority_years_to !== null ? (int) $policy->seniority_years_to : 999;
            $newTo = $to !== null ? (int) $to : 999;

            // Verificación de solapamiento de rangos
            if (max($from, $pFrom) <= min($newTo, $pTo)) {
                return true;
            }
        }

        return false;
    }
}
