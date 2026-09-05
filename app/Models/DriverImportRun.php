<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DriverImportRun extends Model
{
    use HasFactory;

    protected $fillable = [
        'source_file',
        'tipo_periodo',
        'rows_processed',
        'rows_created',
        'rows_updated',
        'rows_with_errors',
        'created_by',
        'summary',
    ];

    protected $casts = [
        'summary' => 'array',
    ];

    public function rows(): HasMany
    {
        return $this->hasMany(DriverImportRunRow::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
