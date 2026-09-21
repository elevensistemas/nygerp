<?php

namespace App\Http\Requests\HR;

use Illuminate\Foundation\Http\FormRequest;

class EmployeeFileUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'document' => [
                'required',
                'file',
                'max:10240', // 10MB
                'mimes:pdf,jpg,jpeg,png,doc,docx',
            ],
            'category' => ['required', 'string', 'max:50'],
            'title' => ['required', 'string', 'max:150'],
            'issue_date' => ['nullable', 'date'],
            'expiration_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'document.required' => 'Debe seleccionar un archivo para adjuntar al legajo.',
            'document.file' => 'El archivo adjunto no es válido.',
            'document.max' => 'El archivo no puede superar el tamaño máximo de 10 MB.',
            'document.mimes' => 'El formato no está permitido. Formatos aceptados: PDF, JPG, PNG, DOC, DOCX.',
            'category.required' => 'Debe seleccionar una categoría de documento.',
            'title.required' => 'Debe ingresar un título identificatorio para el documento.',
        ];
    }
}
