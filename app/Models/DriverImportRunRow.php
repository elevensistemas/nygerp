<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DriverImportRunRow extends Model
{
    use HasFactory;

    protected $fillable = [
        'driver_import_run_id',
        'sheet_name',
        'row_number',
        'status',
        'message',
        'payload',
    ];

    protected $casts = [
        'payload' => 'array',
    ];

    public function run(): BelongsTo
    {
        return $this->belongsTo(DriverImportRun::class, 'driver_import_run_id');
    }
}
