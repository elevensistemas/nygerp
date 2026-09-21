<?php

namespace App\Models\HR;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmployeeFile extends Model
{
    use SoftDeletes;

    protected $table = 'hr_employee_files';

    protected $fillable = [
        'employee_id',
        'category',
        'title',
        'file_path',
        'file_name',
        'file_size',
        'file_hash',
        'file_mime',
        'issue_date',
        'expiration_date',
        'visibility_level',
        'version',
        'notes',
        'is_active',
        'uploaded_by',
    ];

    protected $casts = [
        'issue_date' => 'date',
        'expiration_date' => 'date',
        'is_active' => 'boolean',
        'version' => 'integer',
        'file_size' => 'integer',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function getCategoryLabelAttribute(): string
    {
        $categories = [
            'datos_personales' => 'Datos Personales / DNI',
            'datos_laborales' => 'Datos Laborales / Alta AFIP',
            'curriculum' => 'Currículum Vitae',
            'dni_documentos' => 'Documentación Personal',
            'contratos' => 'Contratos y Anexos',
            'apto_medico' => 'Apto Médico / Exámenes',
            'declaraciones_juradas' => 'Declaraciones Juradas',
            'capacitaciones' => 'Capacitaciones y Cursos',
            'certificados' => 'Certificados y Títulos',
            'recibos_sueldo' => 'Recibos de Sueldo',
            'sanciones_comunicaciones' => 'Sanciones / Notificaciones',
            'egreso' => 'Documentación de Egreso',
            'otros' => 'Otros Archivos',
        ];
        return $categories[$this->category] ?? ucfirst($this->category);
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

    public function isExpired(): bool
    {
        return $this->expiration_date && $this->expiration_date->isPast();
    }
}
