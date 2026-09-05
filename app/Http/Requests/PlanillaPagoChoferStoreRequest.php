<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PlanillaPagoChoferStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'fecha' => ['required', 'date'],
            'observaciones' => ['nullable', 'string'],
            'recibo_ids' => ['required', 'array', 'min:1'],
            'recibo_ids.*' => ['integer', 'distinct', 'exists:recibos_chofer,id'],
        ];
    }
}
