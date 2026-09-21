<?php

namespace App\Models\HR;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocumentType extends Model
{
    protected $table = 'hr_document_types';

    protected $fillable = [
        'name',
        'code',
        'requires_signature',
        'is_sensitive',
        'allow_ex_employee_access',
        'is_active',
    ];

    protected $casts = [
        'requires_signature' => 'boolean',
        'is_sensitive' => 'boolean',
        'allow_ex_employee_access' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class, 'document_type_id');
    }
}
