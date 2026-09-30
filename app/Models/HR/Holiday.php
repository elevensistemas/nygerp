<?php

namespace App\Models\HR;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Holiday extends Model
{
    protected $table = 'hr_holidays';

    protected $fillable = [
        'name',
        'date',
        'holiday_date',
        'year',
        'type', // national, provincial, company, non_working
        'is_recurring', // true ONLY for fixed date holidays
        'branch_id',
        'is_active',
        'description',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'date' => 'date',
        'is_recurring' => 'boolean',
        'is_active' => 'boolean',
        'year' => 'integer',
    ];

    public function getHolidayDateAttribute()
    {
        return $this->attributes['date'] ?? null;
    }

    public function setHolidayDateAttribute($value)
    {
        $this->attributes['date'] = $value;
    }

    public function getNotesAttribute()
    {
        return $this->attributes['description'] ?? null;
    }

    public function setNotesAttribute($value)
    {
        $this->attributes['description'] = $value;
    }

    public function getTypeAttribute()
    {
        return $this->attributes['type'] ?? 'national';
    }

    public function setTypeAttribute($value)
    {
        $this->attributes['type'] = $value;
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
