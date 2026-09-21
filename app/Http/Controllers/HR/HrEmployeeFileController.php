<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Http\Requests\HR\EmployeeFileUploadRequest;
use App\Models\HR\Employee;
use App\Models\HR\EmployeeFile;
use App\Services\HR\HrDocumentStorageService;
use Illuminate\Http\Request;

class HrEmployeeFileController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'terms.accepted', 'hr.access:admin']);
    }

    public function store(EmployeeFileUploadRequest $request, Employee $employee, HrDocumentStorageService $storageService)
    {
        $file = $storageService->storeFile(
            $employee,
            $request->file('document'),
            $request->validated(),
            auth()->user()
        );

        return redirect()->route('rrhh.employees.show', ['employee' => $employee, 'tab' => 'legajo'])
            ->with('ok', "Documento '{$file->title}' incorporado con éxito al legajo digital.");
    }

    public function download(Employee $employee, EmployeeFile $file, HrDocumentStorageService $storageService)
    {
        if ((int) $file->employee_id !== (int) $employee->id) {
            abort(404, 'Documento no encontrado para el colaborador indicado.');
        }

        if (!$file->is_active) {
            abort(404, 'El documento solicitado se encuentra anulado.');
        }

        return $storageService->getFileResponse($file, false);
    }

    public function preview(Employee $employee, EmployeeFile $file, HrDocumentStorageService $storageService)
    {
        if ((int) $file->employee_id !== (int) $employee->id) {
            abort(404, 'Documento no encontrado para el colaborador indicado.');
        }

        if (!$file->is_active) {
            abort(404, 'El documento solicitado se encuentra anulado.');
        }

        return $storageService->getFileResponse($file, true);
    }

    public function voidFile(Request $request, Employee $employee, EmployeeFile $file, HrDocumentStorageService $storageService)
    {
        if ((int) $file->employee_id !== (int) $employee->id) {
            abort(404, 'Documento no encontrado para el colaborador indicado.');
        }

        if (!$file->is_active) {
            return redirect()->route('rrhh.employees.show', ['employee' => $employee, 'tab' => 'legajo'])
                ->with('error', "El documento '{$file->title}' ya fue anulado previamente.");
        }

        $request->validate([
            'reason' => 'required|string|max:255',
        ], [
            'reason.required' => 'Debe ingresar el motivo de anulación del documento.',
        ]);

        $storageService->voidFile($file, $request->reason);

        return redirect()->route('rrhh.employees.show', ['employee' => $employee, 'tab' => 'legajo'])
            ->with('ok', "Documento '{$file->title}' anulado del legajo.");
    }
}
