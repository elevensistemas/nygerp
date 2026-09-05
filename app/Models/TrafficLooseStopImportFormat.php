<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrafficLooseStopImportFormat extends Model
{
    protected $fillable = [
        'party_id',
        'name',
        'sheet',
        'start_row',
        'field_mappings',
        'address_includes_locality',
        'order_date_cell_column',
        'order_date_cell_row',
    ];

    protected $casts = [
        'start_row' => 'integer',
        'field_mappings' => 'array',
        'address_includes_locality' => 'boolean',
        'order_date_cell_row' => 'integer',
    ];

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    public static function fieldLabels(): array
    {
        return [
            'code' => 'Referencia / código',
            'address' => 'Dirección',
            'order_date' => 'Fecha de pedido (por fila)',
            'locality' => 'Localidad (si está separada)',
            'notes' => 'Notas',
            'priority' => 'Prioridad',
            'latitude' => 'Latitud',
            'longitude' => 'Longitud',
            'sender_name' => 'Remitente - Nombre',
            'sender_address' => 'Remitente - Dirección',
            'sender_contact' => 'Remitente - Contacto',
            'recipient_name' => 'Destinatario - Nombre',
            'recipient_address' => 'Destinatario - Dirección',
            'recipient_contact' => 'Destinatario - Contacto',
            'tracking_number' => 'Tracking / número de seguimiento',
            'length' => 'Largo (cm)',
            'width' => 'Ancho (cm)',
            'height' => 'Alto (cm)',
            'weight_actual' => 'Peso real (kg)',
            'weight_volumetric' => 'Peso volumétrico (kg)',
            'content_description' => 'Contenido',
            'declared_value' => 'Valor declarado',
            'barcode' => 'Código de barras',
            'qr_code' => 'Código QR',
        ];
    }

    public function columnFor(string $field): ?string
    {
        return $this->field_mappings[$field]['column'] ?? null;
    }

    public function rowFor(string $field): ?int
    {
        $value = $this->field_mappings[$field]['row'] ?? null;
        return is_numeric($value) ? (int) $value : null;
    }
}
