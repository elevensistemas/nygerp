<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Http\Requests\HR\PositionRequest;
use App\Models\HR\Department;
use App\Models\HR\Position;
use App\Services\HR\HrAuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HrPositionController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'terms.accepted', 'hr.access:admin']);
    }

    public function index(Request $request)
    {
        $query = Position::with('department')
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

        if ($request->filled('department_id')) {
            $query->where('department_id', $request->department_id);
        }

        if ($request->filled('status')) {
            $query->where('is_active', $request->status === 'active');
        }

        $positions = $query->orderBy('name')->paginate(15)->withQueryString();
        $departments = Department::where('is_active', true)->orderBy('name')->get();

        return view('hr.positions.index', compact('positions', 'departments'));
    }

    public function create()
    {
        $departments = Department::where('is_active', true)->orderBy('name')->get();
        return view('hr.positions.create', compact('departments'));
    }

    public function store(PositionRequest $request)
    {
        $position = DB::transaction(function () use ($request) {
            $data = $request->validated();
            $data['is_active'] = $request->has('is_active') ? (bool) $request->is_active : true;

            $pos = Position::create($data);

            HrAuditService::log(
                'position_created',
                'Position',
                $pos->id,
                null,
                $pos->toArray(),
                "Creación del puesto '{$pos->name}' ({$pos->code})"
            );

            return $pos;
        });

        return redirect()->route('rrhh.positions.index')
            ->with('ok', "Puesto '{$position->name}' creado con éxito.");
    }

    public function edit(Position $position)
    {
        $departments = Department::where('is_active', true)->orderBy('name')->get();
        return view('hr.positions.edit', compact('position', 'departments'));
    }

    public function update(PositionRequest $request, Position $position)
    {
        $oldValues = $position->toArray();

        DB::transaction(function () use ($request, $position, $oldValues) {
            $data = $request->validated();
            $data['is_active'] = $request->has('is_active') ? (bool) $request->is_active : false;

            $position->update($data);

            HrAuditService::log(
                'position_updated',
                'Position',
                $position->id,
                $oldValues,
                $position->fresh()->toArray(),
                "Actualización del puesto '{$position->name}'"
            );
        });

        return redirect()->route('rrhh.positions.index')
            ->with('ok', "Puesto '{$position->name}' actualizado correctamente.");
    }

    public function destroy(Position $position)
    {
        if ($position->employees()->exists()) {
            return redirect()->route('rrhh.positions.index')
                ->with('error', "No es posible eliminar el puesto '{$position->name}' porque posee colaboradores asignados.");
        }

        $posName = $position->name;
        $oldValues = $position->toArray();

        DB::transaction(function () use ($position, $oldValues, $posName) {
            $position->delete();

            HrAuditService::log(
                'position_deleted',
                'Position',
                $position->id,
                $oldValues,
                null,
                "Eliminación del puesto '{$posName}'"
            );
        });

        return redirect()->route('rrhh.positions.index')
            ->with('ok', "Puesto '{$posName}' eliminado correctamente.");
    }

    public function toggleStatus(Position $position)
    {
        $oldStatus = $position->is_active;
        $position->update(['is_active' => !$oldStatus]);

        HrAuditService::log(
            'position_status_toggle',
            'Position',
            $position->id,
            ['is_active' => $oldStatus],
            ['is_active' => $position->is_active],
            "Cambio de estado del puesto '{$position->name}' a " . ($position->is_active ? 'Activo' : 'Inactivo')
        );

        return redirect()->back()
            ->with('ok', "Estado del puesto '{$position->name}' actualizado a " . ($position->is_active ? 'Activo' : 'Inactivo') . ".");
    }
}
