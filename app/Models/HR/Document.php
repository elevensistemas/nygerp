<?php

namespace App\Models\HR;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Document extends Model
{
    use SoftDeletes;

    protected $table = 'hr_documents';

    protected $fillable = [
        'document_type_id',
        'title',
        'description',
        'file_path',
        'file_name',
        'file_size',
        'file_hash',
        'file_mime',
        'scope',
        'department_id',
        'branch_id',
        'employee_id',
        'published_at',
        'due_date',
        'version',
        'status',
        'created_by',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'due_date' => 'date',
        'version' => 'integer',
        'file_size' => 'integer',
    ];

    public function documentType(): BelongsTo
    {
        return $this->belongsTo(DocumentType::class, 'document_type_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(DocumentAssignment::class, 'document_id');
    }

    public function getFileSizeFormattedAttribute(): string
    {
        $bytes = $this->file_size;
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        }
        return $bytes . ' B';
    }

    public function getStatusBadgeAttribute(): string
    {
        switch ($this->status) {
            case 'borrador':
                return '<span class="badge bg-secondary">Borrador</span>';
            case 'publicado':
                return '<span class="badge bg-success">Publicado</span>';
            case 'archivado':
                return '<span class="badge bg-dark">Archivado</span>';
            case 'anulado':
                return '<span class="badge bg-danger">Anulado</span>';
            default:
                return '<span class="badge bg-light text-dark">' . ucfirst($this->status) . '</span>';
        }
    }
}
