<?php

namespace App\Services\HR;

use App\Models\HR\Employee;
use App\Models\HR\EmployeeFile;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

class HrDocumentStorageService
{
    const DISK = 'local';
    const MAX_FILE_SIZE = 10485760; // 10MB in bytes
    const ALLOWED_EXTENSIONS = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'];
    const ALLOWED_MIMES = [
        'application/pdf',
        'image/jpeg',
        'image/png',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ];

    /**
     * Almacena de forma segura un archivo privado en el legajo digital del empleado.
     */
    public function storeFile(Employee $employee, UploadedFile $uploadedFile, array $data, ?User $uploader = null): EmployeeFile
    {
        $extension = strtolower($uploadedFile->getClientOriginalExtension());
        $mime = $uploadedFile->getMimeType();
        $fileSize = $uploadedFile->getSize();

        // Validación estricta de extensión y MIME
        if (!in_array($extension, self::ALLOWED_EXTENSIONS)) {
            throw new HttpException(422, 'El formato de archivo .' . $extension . ' no está permitido.');
        }

        if (!in_array($mime, self::ALLOWED_MIMES)) {
            throw new HttpException(422, 'El tipo MIME (' . $mime . ') no es válido para la documentación del legajo.');
        }

        if ($fileSize > self::MAX_FILE_SIZE) {
            throw new HttpException(422, 'El archivo excede el tamaño máximo permitido de 10 MB.');
        }

        // Generar nombre físico seguro (UUID v4)
        $physicalName = Str::uuid()->toString() . '.' . $extension;
        $hash = hash_file('sha256', $uploadedFile->getRealPath());

        // Directorio privado aislado: hr/employee_files/{employee_id}/
        $folder = 'hr/employee_files/' . $employee->id;
        $path = $uploadedFile->storeAs($folder, $physicalName, ['disk' => self::DISK]);

        $employeeFile = EmployeeFile::create([
            'employee_id' => $employee->id,
            'category' => $data['category'] ?? 'otros',
            'title' => $data['title'] ?? pathinfo($uploadedFile->getClientOriginalName(), PATHINFO_FILENAME),
            'file_path' => $path,
            'file_name' => $uploadedFile->getClientOriginalName(),
            'file_size' => $fileSize,
            'file_hash' => $hash,
            'file_mime' => $mime,
            'issue_date' => $data['issue_date'] ?? null,
            'expiration_date' => $data['expiration_date'] ?? null,
            'visibility_level' => $data['visibility_level'] ?? 'privado_rrhh',
            'version' => 1,
            'notes' => $data['notes'] ?? null,
            'is_active' => true,
            'uploaded_by' => $uploader ? $uploader->id : auth()->id(),
        ]);

        HrAuditService::log(
            'document_upload',
            'EmployeeFile',
            $employeeFile->id,
            null,
            [
                'employee_id' => $employee->id,
                'category' => $employeeFile->category,
                'file_name' => $employeeFile->file_name,
                'file_size' => $fileSize,
                'file_hash' => $hash,
            ],
            "Carga de documento '{$employeeFile->title}' en legajo de {$employee->full_name}"
        );

        return $employeeFile;
    }

    /**
     * Genera la descarga o visualización segura previniendo path traversal y validando existencia.
     */
    public function getFileResponse(EmployeeFile $file, bool $preview = false)
    {
        // Prevención de path traversal
        $sanitizedPath = str_replace(['..', "\0"], '', $file->file_path);
        
        if (!Storage::disk(self::DISK)->exists($sanitizedPath)) {
            abort(404, 'El archivo solicitado no se encuentra en el almacenamiento seguro.');
        }

        $fullPath = Storage::disk(self::DISK)->path($sanitizedPath);

        // Registro de auditoría
        HrAuditService::log(
            $preview ? 'document_preview' : 'document_download',
            'EmployeeFile',
            $file->id,
            null,
            ['preview' => $preview],
            ($preview ? "Visualización" : "Descarga") . " de documento '{$file->title}' de {$file->employee->full_name}"
        );

        $safeDownloadName = Str::slug($file->title) . '.' . pathinfo($file->file_name, PATHINFO_EXTENSION);

        // Previsualización inline segura para PDF e imágenes
        if ($preview && in_array($file->file_mime, ['application/pdf', 'image/jpeg', 'image/png'])) {
            return response()->file($fullPath, [
                'Content-Type' => $file->file_mime,
                'Content-Disposition' => 'inline; filename="' . $safeDownloadName . '"',
                'X-Content-Type-Options' => 'nosniff',
            ]);
        }

        // Descarga obligatoria para el resto de extensiones
        return Storage::disk(self::DISK)->download($sanitizedPath, $safeDownloadName, [
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * Realiza la anulación / baja lógica de un documento del legajo.
     */
    public function voidFile(EmployeeFile $file, string $reason): void
    {
        $oldState = ['is_active' => $file->is_active, 'notes' => $file->notes];
        
        $file->update([
            'is_active' => false,
            'notes' => trim($file->notes . "\n[ANULADO " . now()->format('d/m/Y H:i') . "]: " . $reason),
        ]);

        HrAuditService::log(
            'document_void',
            'EmployeeFile',
            $file->id,
            $oldState,
            ['is_active' => false, 'reason' => $reason],
            "Anulación de documento '{$file->title}' en legajo de {$file->employee->full_name}. Motivo: {$reason}"
        );
    }
}
