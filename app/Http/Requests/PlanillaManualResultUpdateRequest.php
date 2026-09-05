<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PlanillaManualResultUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'resultado' => ['nullable', 'integer', 'exists:driver_payment_types,id'],
        ];
    }
}
