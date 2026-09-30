<?php

namespace App\Models\HR;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeaveBalance extends Model
{
    protected $table = 'hr_leave_balances';

    protected $fillable = [
        'employee_id',
        'leave_type_id',
        'leave_policy_id',
        'policy_snapshot',
        'period_year',
        'assigned_days',
        'used_days',
        'transferred_days',
        'adjustment_days',
        'pending_days',
        'available_days',
        'expiration_date',
        'is_closed',
        'closed_at',
        'closed_by',
        'closure_reason',
    ];

    protected $casts = [
        'assigned_days' => 'decimal:1',
        'used_days' => 'decimal:1',
        'transferred_days' => 'decimal:1',
        'adjustment_days' => 'decimal:1',
        'pending_days' => 'decimal:1',
        'available_days' => 'decimal:1',
        'expiration_date' => 'date',
        'is_closed' => 'boolean',
        'closed_at' => 'datetime',
        'policy_snapshot' => 'array',
        'period_year' => 'integer',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class, 'leave_type_id');
    }

    public function leavePolicy(): BelongsTo
    {
        return $this->belongsTo(LeavePolicy::class, 'leave_policy_id');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(LeaveRequestAllocation::class, 'leave_balance_id');
    }

    public function adjustments(): HasMany
    {
        return $this->hasMany(LeaveBalanceAdjustment::class, 'leave_type_id', 'leave_type_id')
            ->where('employee_id', $this->employee_id)
            ->where('period_year', $this->period_year);
    }

    public function getPositiveAdjustmentsAttribute()
    {
        return (float) LeaveBalanceAdjustment::where('employee_id', $this->employee_id)
            ->where('leave_type_id', $this->leave_type_id)
            ->where('period_year', $this->period_year)
            ->where('difference', '>', 0)
            ->sum('difference');
    }

    public function getNegativeAdjustmentsAttribute()
    {
        return (float) abs(LeaveBalanceAdjustment::where('employee_id', $this->employee_id)
            ->where('leave_type_id', $this->leave_type_id)
            ->where('period_year', $this->period_year)
            ->where('difference', '<', 0)
            ->sum('difference'));
    }

    /**
     * Total de días otorgados: asignados + trasladados + ajustes manuales
     */
    public function getTotalGrantedAttribute(): float
    {
        return (float) $this->assigned_days + (float) $this->transferred_days + (float) $this->adjustment_days;
    }

    /**
     * Recalcula el saldo disponible neto:
     * Saldo disponible = Total otorgado - (used_days + pending_days)
     */
    public function recalculateAvailable(): void
    {
        $totalGranted = (float) $this->assigned_days + (float) $this->transferred_days + (float) $this->adjustment_days;
        $this->available_days = $totalGranted - ((float) $this->used_days + (float) $this->pending_days);
        $this->save();
    }
}
