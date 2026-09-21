<?php

namespace App\Http\Requests\HR;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PositionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $posId = $this->route('position') ? (is_object($this->route('position')) ? $this->route('position')->id : $this->route('position')) : null;

        return [
            'department_id' => ['nullable', 'integer', 'exists:hr_departments,id'],
            'name' => ['required', 'string', 'max:100'],
            'code' => [
                'required',
                'string',
                'max:30',
                Rule::unique('hr_positions', 'code')->ignore($posId),
            ],
            'description' => ['nullable', 'string', 'max:500'],
            'requirements' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'El nombre del puesto es obligatorio.',
            'code.required' => 'El código de puesto es obligatorio.',
            'code.unique' => 'El código de puesto ya se encuentra registrado.',
        ];
    }
}
