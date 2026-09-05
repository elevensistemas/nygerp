<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TransportistaImportFormat extends Model
{
    protected $fillable = [
        'name',
        'sheet',
        'start_row',
        'field_mappings',
    ];

    protected $casts = [
        'start_row' => 'integer',
        'field_mappings' => 'array',
    ];

    public static function fieldLabels(): array
    {
        return [
            'name' => 'Chofer',
            'status' => 'Estado',
            'condition' => 'Condición',
            'phone' => 'Contacto',
            'base_location' => 'Zona',
            'zones' => 'Zonas comunes (coma)',
            'client_name' => 'Cliente',
            'phone_alt' => 'Número alternativo',
            'dni' => 'DNI',
            'birth_date' => 'Fecha de nacimiento',
            'license_expires_at' => 'Registro (vencimiento)',
            'personal_insurance' => 'Seguro personal',
            'address_certificate' => 'Cert. de domicilio',
            'email' => 'Email',
            'criminal_record_certificate' => 'Cert. antecedentes penales',
            'cbu' => 'CBU',
            'tax_id' => 'CUIT/CUIL',
            'monotributo' => 'Monotributo',
            'hire_date' => 'Fecha de ingreso',
        ];
    }
}
