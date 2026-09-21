<?php

namespace App\Models\HR;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Bulletin extends Model
{
    use SoftDeletes;

    protected $table = 'hr_bulletins';

    protected $fillable = [
        'title',
        'summary',
        'content',
        'category',
        'is_featured',
        'requires_read_confirmation',
        'image_path',
        'attachment_path',
        'target_scope',
        'department_id',
        'branch_id',
        'status',
        'published_at',
        'expires_at',
        'created_by',
    ];

    protected $casts = [
        'is_featured' => 'boolean',
        'requires_read_confirmation' => 'boolean',
        'published_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reads(): HasMany
    {
        return $this->hasMany(BulletinRead::class, 'bulletin_id');
    }

    public function isReadBy(int $employeeId): bool
    {
        return $this->reads()->where('employee_id', $employeeId)->exists();
    }

    public function getCategoryBadgeAttribute(): string
    {
        switch ($this->category) {
            case 'urgente':
                return '<span class="badge bg-danger"><i class="fa-solid fa-triangle-exclamation me-1"></i>Urgente</span>';
            case 'comunicado':
                return '<span class="badge bg-primary"><i class="fa-solid fa-bullhorn me-1"></i>Comunicado</span>';
            case 'novedad':
                return '<span class="badge bg-info text-dark"><i class="fa-solid fa-bell me-1"></i>Novedad</span>';
            case 'politica':
                return '<span class="badge bg-dark"><i class="fa-solid fa-scale-balanced me-1"></i>Política</span>';
            case 'beneficio':
                return '<span class="badge bg-success"><i class="fa-solid fa-gift me-1"></i>Beneficio</span>';
            default:
                return '<span class="badge bg-warning text-dark"><i class="fa-solid fa-newspaper me-1"></i>Noticia</span>';
        }
    }
}
