<?php

namespace App\Models\HR;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveRequestAllocation extends Model
{
    protected $table = 'hr_leave_request_allocations';

    protected $fillable = [
        'leave_request_id',
        'leave_balance_id',
        'period_year',
        'days_allocated',
        'status', // pending, used, released
    ];

    protected $casts = [
        'days_allocated' => 'decimal:1',
        'period_year' => 'integer',
    ];

    public function leaveRequest(): BelongsTo
    {
        return $this->belongsTo(LeaveRequest::class, 'leave_request_id');
    }

    public function leaveBalance(): BelongsTo
    {
        return $this->belongsTo(LeaveBalance::class, 'leave_balance_id');
    }
}
