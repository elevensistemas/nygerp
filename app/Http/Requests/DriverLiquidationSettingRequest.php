<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DriverLiquidationSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'tipo_periodo' => ['required', 'in:quincenal,mensual'],
            'quincena' => ['nullable', 'integer', 'in:1,2'],
            'desde' => ['required', 'integer', 'min:1', 'max:31'],
            'hasta' => ['required', 'integer', 'min:1', 'max:31', 'gte:desde'],
            'activo' => ['nullable', 'boolean'],
            'column_map_json' => ['nullable', 'string'],
        ];
    }
}
