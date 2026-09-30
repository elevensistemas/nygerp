<?php

namespace App\Models\HR;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveApprovalLog extends Model
{
    protected $table = 'hr_leave_approval_logs';

    protected $fillable = [
        'leave_request_id',
        'approver_id',
        'user_id',
        'level',
        'action',
        'comment',
        'comments',
        'ip_address',
        'user_agent',
    ];

    public function getUserIdAttribute()
    {
        return $this->attributes['approver_id'] ?? null;
    }

    public function setUserIdAttribute($value)
    {
        $this->attributes['approver_id'] = $value;
    }

    public function getCommentsAttribute()
    {
        return $this->attributes['comment'] ?? null;
    }

    public function setCommentsAttribute($value)
    {
        $this->attributes['comment'] = $value;
    }

    public function leaveRequest(): BelongsTo
    {
        return $this->belongsTo(LeaveRequest::class, 'leave_request_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
    }
}
