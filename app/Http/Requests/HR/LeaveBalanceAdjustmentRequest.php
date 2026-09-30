<?php

namespace App\Http\Requests\HR;

use App\Models\HR\LeaveBalance;
use Illuminate\Foundation\Http\FormRequest;

class LeaveBalanceAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $merge = [];
        $balance = $this->route('balance');
        if ($balance) {
            $balanceModel = is_numeric($balance) ? LeaveBalance::find($balance) : $balance;
            if ($balanceModel) {
                if (!$this->filled('employee_id')) {
                    $merge['employee_id'] = $balanceModel->employee_id;
                }
                if (!$this->filled('leave_type_id')) {
                    $merge['leave_type_id'] = $balanceModel->leave_type_id;
                }
                if (!$this->filled('period_year')) {
                    $merge['period_year'] = $balanceModel->period_year;
                }
            }
        }

        if ($this->filled('days') && !$this->filled('amount')) {
            $days = (float) $this->input('days');
            $type = $this->input('type', 'positive');
            $merge['amount'] = ($type === 'positive') ? $days : -$days;
        } elseif ($this->filled('amount') && !$this->filled('days')) {
            $amount = (float) $this->input('amount');
            $merge['days'] = abs($amount);
            $merge['type'] = $amount >= 0 ? 'positive' : 'negative';
        }

        if (!empty($merge)) {
            $this->merge($merge);
        }
    }

    public function rules(): array
    {
        return [
            'employee_id' => ['nullable', 'integer', 'exists:hr_employees,id'],
            'leave_type_id' => ['nullable', 'integer', 'exists:hr_leave_types,id'],
            'period_year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'amount' => ['nullable', 'numeric', 'not_in:0'],
            'days' => ['nullable', 'numeric', 'gt:0'],
            'type' => ['nullable', 'string', 'in:positive,negative'],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'employee_id.required' => 'Debe seleccionar el colaborador.',
            'leave_type_id.required' => 'Debe seleccionar el tipo de ausencia.',
            'period_year.required' => 'Debe especificar el año o período.',
            'amount.required' => 'Debe ingresar la cantidad de días a ajustar.',
            'amount.not_in' => 'El monto del ajuste no puede ser cero.',
            'days.gt' => 'La cantidad de días debe ser mayor a cero.',
            'reason.required' => 'El motivo del ajuste manual es obligatorio para preservar la auditoría.',
            'reason.min' => 'El motivo debe contener al menos 5 caracteres.',
        ];
    }
}
