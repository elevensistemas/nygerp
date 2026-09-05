<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DriverLogisticsRecordLog extends Model
{
    use HasFactory;

    protected $table = 'driver_logistics_record_logs';

    protected $fillable = [
        'driver_logistics_record_id',
        'user_id',
        'action',
        'details',
    ];

    public function record(): BelongsTo
    {
        return $this->belongsTo(DriverLogisticsRecord::class, 'driver_logistics_record_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
