<?php

namespace App\Models\HR;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProfileRequest extends Model
{
    protected $table = 'hr_profile_requests';

    protected $fillable = [
        'employee_id',
        'user_id',
        'field_name',
        'old_value',
        'new_value',
        'status',
        'reviewed_by',
        'reviewed_at',
        'rejection_reason',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function getFieldLabelAttribute(): string
    {
        $labels = [
            'phone' => 'Teléfono',
            'personal_email' => 'Email Personal',
            'address' => 'Domicilio',
            'city' => 'Ciudad',
            'province' => 'Provincia',
            'postal_code' => 'Código Postal',
            'emergency_contact_name' => 'Contacto de Emergencia',
            'emergency_contact_phone' => 'Teléfono de Emergencia',
            'emergency_contact_relationship' => 'Vínculo de Emergencia',
            'marital_status' => 'Estado Civil',
            'avatar_path' => 'Foto de Perfil',
        ];
        return $labels[$this->field_name] ?? ucfirst($this->field_name);
    }
}
