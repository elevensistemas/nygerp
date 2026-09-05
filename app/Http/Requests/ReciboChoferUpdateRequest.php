<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReciboChoferUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'observaciones' => ['nullable', 'string'],
            'fecha_emision' => ['nullable', 'date'],
            'plaza' => ['nullable', 'string', 'max:255'],
            'factura_fecha' => ['nullable', 'date'],
            'factura_ref' => ['nullable', 'string', 'max:255'],
            'factura_observaciones' => ['nullable', 'string'],
            'factura_pdf' => ['nullable', 'file', 'mimes:pdf', 'max:10240'],
            'estado' => ['nullable', 'in:cargado,en_planilla,pagado,pendiente_pago,anulado'],
        ];
    }
}
