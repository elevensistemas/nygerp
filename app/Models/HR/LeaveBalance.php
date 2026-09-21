<?php

namespace App\Models\HR;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveBalance extends Model
{
    protected $table = 'hr_leave_balances';

    protected $fillable = [
        'employee_id',
        'leave_type_id',
        'period_year',
        'assigned_days',
        'used_days',
        'transferred_days',
        'pending_days',
        'available_days',
        'expiration_date',
    ];

    protected $casts = [
        'assigned_days' => 'decimal:1',
        'used_days' => 'decimal:1',
        'transferred_days' => 'decimal:1',
        'pending_days' => 'decimal:1',
        'available_days' => 'decimal:1',
        'expiration_date' => 'date',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class, 'leave_type_id');
    }

    /**
     * Recalcula el saldo disponible neto
     */
    public function recalculateAvailable(): void
    {
        $this->available_days = ($this->assigned_days + $this->transferred_days) - ($this->used_days + $this->pending_days);
        $this->save();
    }
}
