<?php

namespace App\Http\Requests\HR;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BranchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $branchId = $this->route('branch') ? (is_object($this->route('branch')) ? $this->route('branch')->id : $this->route('branch')) : null;

        return [
            'name' => ['required', 'string', 'max:100'],
            'code' => [
                'required',
                'string',
                'max:30',
                Rule::unique('hr_branches', 'code')->ignore($branchId),
            ],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'province' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:50'],
            'manager_name' => ['nullable', 'string', 'max:100'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'El nombre de la sucursal/base es obligatorio.',
            'code.required' => 'El código de la sucursal es obligatorio.',
            'code.unique' => 'El código de sucursal ya se encuentra registrado.',
        ];
    }
}
