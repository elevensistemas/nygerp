<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Http\Requests\HR\EmployeeRequest;
use App\Models\HR\Agreement;
use App\Models\HR\Branch;
use App\Models\HR\Department;
use App\Models\HR\Employee;
use App\Models\HR\HrAuditLog;
use App\Models\HR\LeaveType;
use App\Models\HR\Position;
use App\Models\User;
use App\Services\HR\HrAuditService;
use App\Services\HR\HrLeaveCalculationService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class HrEmployeeController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'terms.accepted', 'hr.access:admin']);
    }

    public function index(Request $request)
    {
        $query = Employee::with(['department', 'position', 'branch', 'manager', 'user']);

        // Búsqueda por texto (nombre, apellido, DNI o legajo)
        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('dni', 'like', "%{$search}%")
                  ->orWhere('file_number', 'like', "%{$search}%");
            });
        }

        // Filtro por Estado
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filtro por Área
        if ($request->filled('department_id')) {
            $query->where('department_id', $request->department_id);
        }

        // Filtro por Puesto
        if ($request->filled('position_id')) {
            $query->where('position_id', $request->position_id);
        }

        // Filtro por Sucursal
        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->branch_id);
        }

        $employees = $query->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate(15)
            ->withQueryString();

        $departments = Department::where('is_active', true)->orderBy('name')->get();
        $positions = Position::where('is_active', true)->orderBy('name')->get();
        $branches = Branch::where('is_active', true)->orderBy('name')->get();

        return view('hr.employees.index', compact('employees', 'departments', 'positions', 'branches'));
    }

    public function create()
    {
        $departments = Department::where('is_active', true)->orderBy('name')->get();
        $positions = Position::where('is_active', true)->orderBy('name')->get();
        $branches = Branch::where('is_active', true)->orderBy('name')->get();
        $agreements = Agreement::where('is_active', true)->orderBy('name')->get();
        $managers = Employee::where('status', 'activo')->orderBy('last_name')->get();

        // Usuarios del sistema que NO tengan ya un empleado vinculado
        $availableUsers = User::whereDoesntHave('hrEmployee')->orderBy('name')->get();

        return view('hr.employees.create', compact(
            'departments',
            'positions',
            'branches',
            'agreements',
            'managers',
            'availableUsers'
        ));
    }

    public function store(EmployeeRequest $request)
    {
        $employee = DB::transaction(function () use ($request) {
            $data = $request->validated();

            // Carga de avatar si corresponde
            if ($request->hasFile('avatar')) {
                $path = $request->file('avatar')->store('avatars/hr', 'public');
                $data['avatar_path'] = $path;
            }

            $emp = Employee::create($data);

            HrAuditService::log(
                'employee_created',
                'Employee',
                $emp->id,
                null,
                $emp->toArray(),
                "Alta del colaborador {$emp->full_name} (Legajo: {$emp->file_number}, DNI: {$emp->dni})"
            );

            return $emp;
        });

        return redirect()->route('rrhh.employees.show', $employee)
            ->with('ok', "Colaborador {$employee->full_name} registrado exitosamente.");
    }

    public function show(Employee $employee, HrLeaveCalculationService $calcService)
    {
        $employee->load([
            'department',
            'position',
            'branch',
            'manager',
            'agreement',
            'user',
            'directReports',
            'files' => function ($q) {
                $q->with('uploader')->latest();
            },
        ]);

        $today = Carbon::today();
        $expirationThreshold = $today->copy()->addDays(30);

        // Agrupación y clasificación de vencimientos del legajo digital
        $filesStats = [
            'total' => $employee->files->where('is_active', true)->count(),
            'expired' => $employee->files->where('is_active', true)->filter(function ($f) use ($today) {
                return $f->expiration_date && $f->expiration_date->lt($today);
            })->count(),
            'expiring_soon' => $employee->files->where('is_active', true)->filter(function ($f) use ($today, $expirationThreshold) {
                return $f->expiration_date && $f->expiration_date->gte($today) && $f->expiration_date->lte($expirationThreshold);
            })->count(),
            'valid' => $employee->files->where('is_active', true)->filter(function ($f) use ($expirationThreshold) {
                return $f->expiration_date && $f->expiration_date->gt($expirationThreshold);
            })->count(),
            'no_expiration' => $employee->files->where('is_active', true)->whereNull('expiration_date')->count(),
        ];

        // Historial reciente de auditoría relacionado al empleado y sus documentos
        $fileIds = $employee->files->pluck('id')->toArray();
        $auditLogs = HrAuditLog::with('user')
            ->where(function ($q) use ($employee, $fileIds) {
                $q->where(function ($sub) use ($employee) {
                    $sub->where('entity_type', 'Employee')
                        ->where('entity_id', $employee->id);
                })->orWhere(function ($sub) use ($fileIds) {
                    $sub->where('entity_type', 'EmployeeFile')
                        ->whereIn('entity_id', $fileIds);
                });
            })
            ->latest()
            ->take(15)
            ->get();

        // Vacaciones y Licencias del colaborador
        $currentYear = $today->year;
        $leaveTypes = LeaveType::where('is_active', true)->orderBy('display_order')->get();
        $leaveBalances = [];
        foreach ($leaveTypes as $lt) {
            $leaveBalances[$lt->id] = $calcService->getOrCreateBalance($employee, $lt, $currentYear);
        }

        $vacationType = $leaveTypes->firstWhere('code', 'VAC');
        $vacationBalance = $vacationType ? ($leaveBalances[$vacationType->id] ?? null) : null;

        $leaveRequests = $employee->leaveRequests()
            ->with(['leaveType', 'approvedByUser'])
            ->latest('date_from')
            ->get();

        return view('hr.employees.show', compact(
            'employee',
            'filesStats',
            'auditLogs',
            'today',
            'expirationThreshold',
            'leaveTypes',
            'leaveBalances',
            'vacationBalance',
            'leaveRequests',
            'currentYear'
        ));
    }

    public function edit(Employee $employee)
    {
        $departments = Department::where('is_active', true)->orderBy('name')->get();
        $positions = Position::where('is_active', true)->orderBy('name')->get();
        $branches = Branch::where('is_active', true)->orderBy('name')->get();
        $agreements = Agreement::where('is_active', true)->orderBy('name')->get();
        $managers = Employee::where('status', 'activo')
            ->where('id', '!=', $employee->id)
            ->orderBy('last_name')
            ->get();

        // Usuarios disponibles: los que no tienen empleado + el usuario actual de este empleado
        $availableUsers = User::where(function ($q) use ($employee) {
            $q->whereDoesntHave('hrEmployee')
              ->orWhere('id', $employee->user_id);
        })->orderBy('name')->get();

        return view('hr.employees.edit', compact(
            'employee',
            'departments',
            'positions',
            'branches',
            'agreements',
            'managers',
            'availableUsers'
        ));
    }

    public function update(EmployeeRequest $request, Employee $employee)
    {
        $oldValues = $employee->toArray();

        DB::transaction(function () use ($request, $employee, $oldValues) {
            $data = $request->validated();

            if ($request->hasFile('avatar')) {
                if ($employee->avatar_path && Storage::disk('public')->exists($employee->avatar_path)) {
                    Storage::disk('public')->delete($employee->avatar_path);
                }
                $data['avatar_path'] = $request->file('avatar')->store('avatars/hr', 'public');
            }

            $employee->update($data);

            HrAuditService::log(
                'employee_updated',
                'Employee',
                $employee->id,
                $oldValues,
                $employee->fresh()->toArray(),
                "Actualización de datos del colaborador {$employee->full_name}"
            );
        });

        return redirect()->route('rrhh.employees.show', $employee)
            ->with('ok', "Datos de {$employee->full_name} actualizados correctamente.");
    }

    public function destroy(Employee $employee)
    {
        // En RR. HH. nunca se realiza eliminación física destructiva.
        // Se efectúa una baja lógica / archivo mediante SoftDeletes, preservando el legajo digital,
        // los archivos físicos y toda la trazabilidad de auditoría.
        $empName = $employee->full_name;
        $oldValues = $employee->toArray();

        DB::transaction(function () use ($employee, $oldValues, $empName) {
            // Soft delete: no borra físicamente el registro de la BD ni sus archivos
            $employee->delete();

            HrAuditService::log(
                'employee_archived',
                'Employee',
                $employee->id,
                $oldValues,
                ['deleted_at' => now()->toDateTimeString()],
                "Baja lógica / Archivo del registro del colaborador {$empName}"
            );
        });

        return redirect()->route('rrhh.employees.index')
            ->with('ok', "Registro del colaborador {$empName} archivado correctamente.");
    }

    public function updateStatus(Request $request, Employee $employee)
    {
        $request->validate([
            'status' => 'required|in:activo,licencia,suspendido,en_onboarding',
        ]);

        $oldStatus = $employee->status;
        $newStatus = $request->status;

        $employee->update(['status' => $newStatus]);

        HrAuditService::log(
            'employee_status_changed',
            'Employee',
            $employee->id,
            ['status' => $oldStatus],
            ['status' => $newStatus],
            "Cambio de estado laboral de {$employee->full_name} a '{$newStatus}'"
        );

        return redirect()->back()
            ->with('ok', "Estado de {$employee->full_name} actualizado a " . ucfirst($newStatus) . ".");
    }

    public function terminate(Request $request, Employee $employee)
    {
        $request->validate([
            'termination_date' => 'required|date|after_or_equal:' . ($employee->hire_date ? $employee->hire_date->toDateString() : '1900-01-01'),
            'termination_reason' => 'required|string|max:255',
        ], [
            'termination_date.required' => 'La fecha de egreso es obligatoria.',
            'termination_date.after_or_equal' => 'La fecha de egreso no puede ser anterior a la fecha de ingreso.',
            'termination_reason.required' => 'Debe indicar el motivo de egreso.',
        ]);

        $oldValues = [
            'status' => $employee->status,
            'termination_date' => $employee->termination_date,
            'termination_reason' => $employee->termination_reason,
        ];

        $employee->update([
            'status' => 'egresado',
            'termination_date' => $request->termination_date,
            'termination_reason' => $request->termination_reason,
        ]);

        HrAuditService::log(
            'employee_terminated',
            'Employee',
            $employee->id,
            $oldValues,
            [
                'status' => 'egresado',
                'termination_date' => $request->termination_date,
                'termination_reason' => $request->termination_reason,
            ],
            "Registro de egreso para el colaborador {$employee->full_name}. Motivo: {$request->termination_reason}"
        );

        return redirect()->route('rrhh.employees.show', $employee)
            ->with('ok', "Egreso registrado correctamente para {$employee->full_name}.");
    }

    public function unlinkUser(Employee $employee)
    {
        $previousUserId = $employee->user_id;
        $employee->update(['user_id' => null]);

        HrAuditService::log(
            'employee_user_unlinked',
            'Employee',
            $employee->id,
            ['user_id' => $previousUserId],
            ['user_id' => null],
            "Desvinculación de usuario del sistema para el colaborador {$employee->full_name}"
        );

        return redirect()->back()
            ->with('ok', "Usuario desvinculado del colaborador {$employee->full_name}.");
    }
}
