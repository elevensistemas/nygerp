<?php

namespace App\Http\Requests\HR;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LeaveTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('counts_as_working_days') && !$this->filled('calculation_unit')) {
            $isWorking = $this->counts_as_working_days;
            $this->merge(['calculation_unit' => ($isWorking && $isWorking != '0' && $isWorking !== 0) ? 'habiles' : 'corridos']);
        }
        if ($this->has('min_anticipation_days') && !$this->filled('min_advance_days')) {
            $this->merge(['min_advance_days' => (int) $this->min_anticipation_days]);
        }
        if (!$this->filled('category')) {
            $this->merge(['category' => 'otro']);
        }
    }

    public function rules(): array
    {
        $leaveTypeId = $this->route('leave_type') ? (is_object($this->route('leave_type')) ? $this->route('leave_type')->id : $this->route('leave_type')) : null;

        return [
            'name' => ['required', 'string', 'max:120'],
            'code' => [
                'required',
                'string',
                'max:30',
                Rule::unique('hr_leave_types', 'code')->ignore($leaveTypeId),
            ],
            'category' => ['required', 'in:vacaciones,medica,examen_estudio,maternidad_paternidad,accidente,personal_con_goce,personal_sin_goce,duelo,matrimonio,otro'],
            'description' => ['nullable', 'string', 'max:500'],
            'days_allowed_per_year' => ['nullable', 'numeric', 'min:0', 'max:365'],
            'max_days_limit' => ['nullable', 'numeric', 'min:0', 'max:365'],
            'calculation_unit' => ['required', 'in:habiles,corridos'],
            'deducts_from_balance' => ['nullable', 'boolean'],
            'requires_attachment' => ['nullable', 'boolean'],
            'requires_approval' => ['nullable', 'boolean'],
            'allows_half_day' => ['nullable', 'boolean'],
            'allows_negative_balance' => ['nullable', 'boolean'],
            'requires_reason' => ['nullable', 'boolean'],
            'min_advance_days' => ['required', 'integer', 'min:0'],
            'display_order' => ['nullable', 'integer', 'min:0'],
            'color' => ['required', 'string', 'max:20'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'El nombre del tipo de ausencia es obligatorio.',
            'code.required' => 'El código identificador es obligatorio.',
            'code.unique' => 'El código ingresado ya se encuentra en uso.',
            'category.required' => 'Debe seleccionar una categoría.',
            'calculation_unit.required' => 'Debe indicar si computa días hábiles o corridos.',
            'color.required' => 'Debe especificar un color identificador.',
        ];
    }
}
