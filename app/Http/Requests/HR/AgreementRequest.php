<?php

namespace App\Http\Requests\HR;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AgreementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $agreementId = $this->route('agreement') ? (is_object($this->route('agreement')) ? $this->route('agreement')->id : $this->route('agreement')) : null;

        return [
            'name' => ['required', 'string', 'max:120'],
            'code' => [
                'required',
                'string',
                'max:30',
                Rule::unique('hr_agreements', 'code')->ignore($agreementId),
            ],
            'description' => ['nullable', 'string', 'max:500'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'El nombre del convenio / política laboral es obligatorio.',
            'code.required' => 'El código identificador es obligatorio.',
            'code.unique' => 'El código de convenio ya se encuentra en uso.',
        ];
    }
}
