<?php

namespace App\Http\Requests\HR;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DepartmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $deptId = $this->route('department') ? (is_object($this->route('department')) ? $this->route('department')->id : $this->route('department')) : null;

        return [
            'name' => ['required', 'string', 'max:100'],
            'code' => [
                'required',
                'string',
                'max:30',
                Rule::unique('hr_departments', 'code')->ignore($deptId),
            ],
            'description' => ['nullable', 'string', 'max:500'],
            'parent_id' => ['nullable', 'integer', 'exists:hr_departments,id'],
            'manager_id' => ['nullable', 'integer', 'exists:hr_employees,id'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'El nombre del área o departamento es obligatorio.',
            'code.required' => 'El código único del área es obligatorio.',
            'code.unique' => 'El código ingresado ya se encuentra registrado para otra área.',
        ];
    }
}
