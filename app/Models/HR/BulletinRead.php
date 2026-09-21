<?php

namespace App\Models\HR;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BulletinRead extends Model
{
    protected $table = 'hr_bulletin_reads';

    protected $fillable = [
        'bulletin_id',
        'employee_id',
        'user_id',
        'read_at',
        'ip_address',
    ];

    protected $casts = [
        'read_at' => 'datetime',
    ];

    public function bulletin(): BelongsTo
    {
        return $this->belongsTo(Bulletin::class, 'bulletin_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
