<?php

namespace App\Http\Requests\HR;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class HolidayRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('holiday_date') && !$this->filled('date')) {
            $this->merge(['date' => $this->holiday_date]);
        }
        if ($this->filled('notes') && !$this->filled('description')) {
            $this->merge(['description' => $this->notes]);
        }
    }

    public function rules(): array
    {
        $holidayId = $this->route('holiday') ? (is_object($this->route('holiday')) ? $this->route('holiday')->id : $this->route('holiday')) : null;

        return [
            'name' => ['required', 'string', 'max:120'],
            'date' => [
                'required',
                'date',
                Rule::unique('hr_holidays', 'date')->ignore($holidayId),
            ],
            'is_recurring' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'description' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'El nombre del feriado es obligatorio.',
            'date.required' => 'La fecha del feriado es obligatoria.',
            'date.unique' => 'Ya existe un feriado registrado para la fecha indicada.',
        ];
    }
}
