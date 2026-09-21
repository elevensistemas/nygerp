<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Http\Requests\HR\DepartmentRequest;
use App\Models\HR\Department;
use App\Models\HR\Employee;
use App\Services\HR\HrAuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HrDepartmentController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'terms.accepted', 'hr.access:admin']);
    }

    public function index(Request $request)
    {
        $query = Department::with(['parent', 'manager'])
            ->withCount(['employees as active_employees_count' => function ($q) {
                $q->where('status', 'activo');
            }]);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('is_active', $request->status === 'active');
        }

        $departments = $query->orderBy('name')->paginate(15)->withQueryString();

        return view('hr.departments.index', compact('departments'));
    }

    public function create()
    {
        $parentDepartments = Department::where('is_active', true)->orderBy('name')->get();
        $potentialManagers = Employee::where('status', 'activo')->orderBy('last_name')->get();

        return view('hr.departments.create', compact('parentDepartments', 'potentialManagers'));
    }

    public function store(DepartmentRequest $request)
    {
        $department = DB::transaction(function () use ($request) {
            $data = $request->validated();
            $data['is_active'] = $request->has('is_active') ? (bool) $request->is_active : true;

            $dept = Department::create($data);

            HrAuditService::log(
                'department_created',
                'Department',
                $dept->id,
                null,
                $dept->toArray(),
                "Creación del área/departamento '{$dept->name}' ({$dept->code})"
            );

            return $dept;
        });

        return redirect()->route('rrhh.departments.index')
            ->with('ok', "Área '{$department->name}' creada con éxito.");
    }

    public function edit(Department $department)
    {
        $parentDepartments = Department::where('is_active', true)
            ->where('id', '!=', $department->id)
            ->orderBy('name')
            ->get();
        $potentialManagers = Employee::where('status', 'activo')->orderBy('last_name')->get();

        return view('hr.departments.edit', compact('department', 'parentDepartments', 'potentialManagers'));
    }

    public function update(DepartmentRequest $request, Department $department)
    {
        $oldValues = $department->toArray();

        DB::transaction(function () use ($request, $department, $oldValues) {
            $data = $request->validated();
            $data['is_active'] = $request->has('is_active') ? (bool) $request->is_active : false;

            $department->update($data);

            HrAuditService::log(
                'department_updated',
                'Department',
                $department->id,
                $oldValues,
                $department->fresh()->toArray(),
                "Actualización del área '{$department->name}'"
            );
        });

        return redirect()->route('rrhh.departments.index')
            ->with('ok', "Área '{$department->name}' actualizada correctamente.");
    }

    public function destroy(Department $department)
    {
        // Validación de integridad: no eliminar físicamente si tiene empleados vinculados
        if ($department->employees()->exists()) {
            return redirect()->route('rrhh.departments.index')
                ->with('error', "No es posible eliminar el área '{$department->name}' porque posee colaboradores asociados.");
        }

        $deptName = $department->name;
        $oldValues = $department->toArray();

        DB::transaction(function () use ($department, $oldValues, $deptName) {
            $department->delete();

            HrAuditService::log(
                'department_deleted',
                'Department',
                $department->id,
                $oldValues,
                null,
                "Eliminación del área '{$deptName}'"
            );
        });

        return redirect()->route('rrhh.departments.index')
            ->with('ok', "Área '{$deptName}' eliminada correctamente.");
    }

    public function toggleStatus(Department $department)
    {
        $oldStatus = $department->is_active;
        $department->update(['is_active' => !$oldStatus]);

        HrAuditService::log(
            'department_status_toggle',
            'Department',
            $department->id,
            ['is_active' => $oldStatus],
            ['is_active' => $department->is_active],
            "Cambio de estado del área '{$department->name}' a " . ($department->is_active ? 'Activa' : 'Inactiva')
        );

        return redirect()->back()
            ->with('ok', "Estado del área '{$department->name}' actualizado a " . ($department->is_active ? 'Activa' : 'Inactiva') . ".");
    }
}
