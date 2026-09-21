<?php

namespace App\Models\HR;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveBalanceAdjustment extends Model
{
    protected $table = 'hr_leave_balance_adjustments';

    protected $fillable = [
        'employee_id',
        'leave_type_id',
        'period_year',
        'previous_balance',
        'new_balance',
        'difference',
        'reason',
        'user_id',
    ];

    protected $casts = [
        'previous_balance' => 'decimal:1',
        'new_balance' => 'decimal:1',
        'difference' => 'decimal:1',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class, 'leave_type_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
