<?php

namespace App\Http\Requests\HR;

use App\Models\HR\LeaveType;
use Illuminate\Foundation\Http\FormRequest;

class LeaveRequestStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $merge = [];
        if ($this->filled('start_date') && !$this->filled('date_from')) {
            $merge['date_from'] = $this->input('start_date');
        } elseif ($this->filled('date_from') && !$this->filled('start_date')) {
            $merge['start_date'] = $this->input('date_from');
        }

        if ($this->filled('end_date') && !$this->filled('date_to')) {
            $merge['date_to'] = $this->input('end_date');
        } elseif ($this->filled('date_to') && !$this->filled('end_date')) {
            $merge['end_date'] = $this->input('date_to');
        }

        if (!empty($merge)) {
            $this->merge($merge);
        }
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if ($validator->errors()->has('date_from')) {
                foreach ($validator->errors()->get('date_from') as $msg) {
                    $validator->errors()->add('start_date', $msg);
                }
            }
            if ($validator->errors()->has('date_to')) {
                foreach ($validator->errors()->get('date_to') as $msg) {
                    $validator->errors()->add('end_date', $msg);
                }
            }
            if ($validator->errors()->has('start_date') && !$validator->errors()->has('date_from')) {
                foreach ($validator->errors()->get('start_date') as $msg) {
                    $validator->errors()->add('date_from', $msg);
                }
            }
            if ($validator->errors()->has('end_date') && !$validator->errors()->has('date_to')) {
                foreach ($validator->errors()->get('end_date') as $msg) {
                    $validator->errors()->add('date_to', $msg);
                }
            }
        });
    }

    public function rules(): array
    {
        $leaveTypeId = $this->input('leave_type_id');
        $leaveType = $leaveTypeId ? LeaveType::find($leaveTypeId) : null;

        $rules = [
            'leave_type_id' => [
                'required',
                'integer',
                function ($attribute, $value, $fail) use ($leaveType) {
                    if (!$leaveType) {
                        $fail('El tipo de ausencia seleccionado no existe.');
                        return;
                    }
                    if (!$leaveType->is_active) {
                        $fail('El tipo de ausencia seleccionado se encuentra desactivado.');
                    }
                },
            ],
            'date_from' => ['required', 'date'],
            'date_to' => ['required', 'date', 'after_or_equal:date_from'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'is_half_day' => ['nullable', 'boolean'],
            'half_day_type' => ['nullable', 'required_if:is_half_day,1,true', 'in:manana,tarde'],
            'notes' => ['nullable', 'string', 'max:500'],
            'confidential_notes' => ['nullable', 'string', 'max:1000'],
            'idempotency_token' => ['nullable', 'string', 'max:64'],
        ];

        // Validación de anticipación mínima si aplica
        if ($leaveType && $leaveType->min_advance_days > 0) {
            $minDate = now()->addDays($leaveType->min_advance_days)->startOfDay()->toDateString();
            $rules['date_from'][] = 'after_or_equal:' . $minDate;
        }

        // Validación de motivo obligatorio
        if ($leaveType && $leaveType->requires_reason) {
            $rules['reason'] = ['required', 'string', 'max:500'];
        } else {
            $rules['reason'] = ['nullable', 'string', 'max:500'];
        }

        // Validación de comprobante obligatorio
        if ($leaveType && $leaveType->requires_attachment) {
            $rules['attachment'] = [
                'required',
                'file',
                'max:10240', // 10MB
                'mimes:pdf,jpg,jpeg,png,doc,docx',
            ];
        } else {
            $rules['attachment'] = [
                'nullable',
                'file',
                'max:10240',
                'mimes:pdf,jpg,jpeg,png,doc,docx',
            ];
        }

        // Si es medio día, la fecha de inicio y fin debe ser la misma
        if ($this->boolean('is_half_day')) {
            $rules['date_to'][] = 'same:date_from';
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'leave_type_id.required' => 'Debe seleccionar el tipo de ausencia.',
            'date_from.required' => 'La fecha de inicio es obligatoria.',
            'date_to.required' => 'La fecha de fin es obligatoria.',
            'date_to.after_or_equal' => 'La fecha de fin debe ser igual o posterior a la fecha de inicio.',
            'date_to.same' => 'Para solicitudes de medio día, la fecha de inicio y fin deben ser iguales.',
            'date_from.after_or_equal' => 'Esta solicitud requiere una anticipación mínima previa.',
            'reason.required' => 'Debe detallar el motivo de la solicitud para este tipo de ausencia.',
            'attachment.required' => 'Es obligatorio adjuntar un comprobante o certificado para este tipo de ausencia.',
            'attachment.mimes' => 'El comprobante debe ser un archivo PDF, imagen o documento Word.',
            'attachment.max' => 'El comprobante adjunto no puede exceder los 10 MB.',
        ];
    }
}
