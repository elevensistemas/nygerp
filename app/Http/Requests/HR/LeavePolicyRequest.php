<?php

namespace App\Http\Requests\HR;

use Illuminate\Foundation\Http\FormRequest;

class LeavePolicyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('min_seniority_years') && !$this->filled('seniority_years_from')) {
            $this->merge(['seniority_years_from' => (int) $this->min_seniority_years]);
        }
        if ($this->has('max_seniority_years') && !$this->filled('seniority_years_to')) {
            $this->merge(['seniority_years_to' => $this->max_seniority_years !== null ? (int) $this->max_seniority_years : null]);
        }
        if ($this->has('allows_carryover') && !$this->filled('allow_transfer')) {
            $this->merge(['allow_transfer' => (bool) $this->allows_carryover]);
        }
        if ($this->has('max_carryover_days') && !$this->filled('max_transferred_days')) {
            $this->merge(['max_transferred_days' => $this->max_carryover_days]);
        }
        if ($this->has('expiration_months') && !$this->filled('transfer_expiration_months')) {
            $this->merge(['transfer_expiration_months' => (int) $this->expiration_months]);
        }
    }

    public function rules(): array
    {
        return [
            'leave_type_id' => ['required', 'integer', 'exists:hr_leave_types,id'],
            'agreement_id' => ['nullable', 'integer', 'exists:hr_agreements,id'],
            'name' => ['nullable', 'string', 'max:150'],
            'seniority_years_from' => ['required', 'integer', 'min:0'],
            'seniority_years_to' => ['nullable', 'integer', 'gte:seniority_years_from'],
            'days_granted' => ['required', 'numeric', 'min:0', 'max:365'],
            'allow_transfer' => ['nullable', 'boolean'],
            'max_transferred_days' => ['nullable', 'numeric', 'min:0'],
            'transfer_expiration_months' => ['nullable', 'integer', 'min:1', 'max:24'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'leave_type_id.required' => 'Debe seleccionar el tipo de ausencia aplicable.',
            'seniority_years_from.required' => 'La antigüedad inicial es obligatoria.',
            'seniority_years_to.gte' => 'La antigüedad final debe ser mayor o igual a la inicial.',
            'days_granted.required' => 'Debe indicar la cantidad de días otorgados.',
        ];
    }
}
