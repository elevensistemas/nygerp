<?php

namespace App\Http\Requests\HR;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $employeeId = $this->route('employee') ? (is_object($this->route('employee')) ? $this->route('employee')->id : $this->route('employee')) : null;

        return [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'dni' => [
                'required',
                'string',
                'max:20',
                Rule::unique('hr_employees', 'dni')->ignore($employeeId)->whereNull('deleted_at'),
            ],
            'cuil' => [
                'nullable',
                'string',
                'max:25',
                Rule::unique('hr_employees', 'cuil')->ignore($employeeId)->whereNull('deleted_at'),
            ],
            'file_number' => [
                'required',
                'string',
                'max:30',
                Rule::unique('hr_employees', 'file_number')->ignore($employeeId)->whereNull('deleted_at'),
            ],
            'user_id' => [
                'nullable',
                'integer',
                'exists:users,id',
                Rule::unique('hr_employees', 'user_id')->ignore($employeeId)->whereNull('deleted_at'),
            ],
            'gender' => ['nullable', 'in:M,F,X,otro'],
            'birth_date' => ['nullable', 'date', 'before:today'],
            'nationality' => ['nullable', 'string', 'max:60'],
            'marital_status' => ['nullable', 'string', 'max:40'],
            'phone' => ['nullable', 'string', 'max:50'],
            'personal_email' => ['nullable', 'email', 'max:150'],
            'work_email' => ['nullable', 'email', 'max:150'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'province' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'emergency_contact_name' => ['nullable', 'string', 'max:150'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:50'],
            'emergency_contact_relationship' => ['nullable', 'string', 'max:60'],
            
            'department_id' => [
                'nullable',
                'integer',
                function ($attribute, $value, $fail) use ($employeeId) {
                    $dept = \App\Models\HR\Department::find($value);
                    if (!$dept) {
                        $fail('El área seleccionada no existe.');
                        return;
                    }
                    if (!$dept->is_active) {
                        if ($employeeId) {
                            $currentEmp = \App\Models\HR\Employee::find($employeeId);
                            if ($currentEmp && (int) $currentEmp->department_id === (int) $value) {
                                return;
                            }
                        }
                        $fail('No es posible asignar un área o departamento que se encuentra inactivo.');
                    }
                },
            ],
            'position_id' => [
                'nullable',
                'integer',
                function ($attribute, $value, $fail) use ($employeeId) {
                    $pos = \App\Models\HR\Position::find($value);
                    if (!$pos) {
                        $fail('El puesto seleccionado no existe.');
                        return;
                    }
                    if (!$pos->is_active) {
                        if ($employeeId) {
                            $currentEmp = \App\Models\HR\Employee::find($employeeId);
                            if ($currentEmp && (int) $currentEmp->position_id === (int) $value) {
                                return;
                            }
                        }
                        $fail('No es posible asignar un puesto que se encuentra inactivo.');
                    }
                },
            ],
            'branch_id' => [
                'nullable',
                'integer',
                function ($attribute, $value, $fail) use ($employeeId) {
                    $br = \App\Models\HR\Branch::find($value);
                    if (!$br) {
                        $fail('La sucursal seleccionada no existe.');
                        return;
                    }
                    if (!$br->is_active) {
                        if ($employeeId) {
                            $currentEmp = \App\Models\HR\Employee::find($employeeId);
                            if ($currentEmp && (int) $currentEmp->branch_id === (int) $value) {
                                return;
                            }
                        }
                        $fail('No es posible asignar una sucursal que se encuentra inactiva.');
                    }
                },
            ],
            'manager_id' => [
                'nullable',
                'integer',
                'exists:hr_employees,id',
                function ($attribute, $value, $fail) use ($employeeId) {
                    if ($employeeId && (int) $value === (int) $employeeId) {
                        $fail('El responsable directo no puede ser el mismo empleado.');
                    }
                },
            ],
            'agreement_id' => [
                'nullable',
                'integer',
                function ($attribute, $value, $fail) use ($employeeId) {
                    $ag = \App\Models\HR\Agreement::find($value);
                    if (!$ag) {
                        $fail('El convenio seleccionado no existe.');
                        return;
                    }
                    if (!$ag->is_active) {
                        if ($employeeId) {
                            $currentEmp = \App\Models\HR\Employee::find($employeeId);
                            if ($currentEmp && (int) $currentEmp->agreement_id === (int) $value) {
                                return;
                            }
                        }
                        $fail('No es posible asignar un convenio que se encuentra inactivo.');
                    }
                },
            ],
            'hire_date' => ['required', 'date'],
            'probation_end_date' => ['nullable', 'date', 'after_or_equal:hire_date'],
            'contract_type' => ['nullable', 'in:indeterminado,plazo_fijo,pasantia,eventual,otro'],
            'status' => ['required', 'in:activo,licencia,suspendido,en_onboarding,egresado'],
            'termination_date' => ['nullable', 'date', 'after_or_equal:hire_date'],
            'termination_reason' => ['nullable', 'string', 'max:255'],
            'salary' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'avatar' => ['nullable', 'image', 'mimes:jpeg,png,jpg', 'max:2048'],
        ];
    }

    public function messages(): array
    {
        return [
            'first_name.required' => 'El nombre del empleado es obligatorio.',
            'last_name.required' => 'El apellido del empleado es obligatorio.',
            'dni.required' => 'El número de DNI es obligatorio.',
            'dni.unique' => 'El DNI ingresado ya se encuentra registrado para otro colaborador.',
            'file_number.required' => 'El número de legajo es obligatorio.',
            'file_number.unique' => 'El número de legajo ya se encuentra en uso.',
            'user_id.unique' => 'El usuario seleccionado ya se encuentra vinculado a otro empleado.',
            'hire_date.required' => 'La fecha de ingreso es obligatoria.',
            'termination_date.after_or_equal' => 'La fecha de egreso no puede ser anterior a la fecha de ingreso.',
            'probation_end_date.after_or_equal' => 'El fin de período de prueba debe ser posterior a la fecha de ingreso.',
            'status.required' => 'El estado laboral es obligatorio.',
            'avatar.max' => 'La foto no puede superar los 2 MB.',
        ];
    }
}
