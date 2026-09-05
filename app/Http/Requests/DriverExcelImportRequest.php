<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DriverExcelImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv'],
            'quincena_option' => ['nullable', 'string', 'in:ambas,1,2'],
        ];
    }
}
