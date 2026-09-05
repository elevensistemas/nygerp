<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReciboChoferItemUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'concepto' => ['required', 'string', 'max:255'],
            'cantidad' => ['nullable', 'numeric'],
            'importe_unitario' => ['nullable', 'numeric'],
            'importe' => ['nullable', 'numeric'],
        ];
    }
}
