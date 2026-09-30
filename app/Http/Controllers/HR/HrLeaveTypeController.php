<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Http\Requests\HR\LeaveTypeRequest;
use App\Models\HR\LeaveType;
use App\Services\HR\HrAuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HrLeaveTypeController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'terms.accepted', 'hr.access:admin']);
    }

    public function index(Request $request)
    {
        $query = LeaveType::withCount(['requests', 'policies']);

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

        $leaveTypes = $query->orderBy('display_order')->orderBy('name')->paginate(15)->withQueryString();

        return view('hr.leave_types.index', compact('leaveTypes'));
    }

    public function create()
    {
        return view('hr.leave_types.create');
    }

    public function store(LeaveTypeRequest $request)
    {
        $leaveType = DB::transaction(function () use ($request) {
            $data = $request->validated();
            $data['is_active'] = $request->has('is_active') ? (bool) $request->is_active : true;
            $data['deducts_from_balance'] = (bool) ($request->deducts_from_balance ?? false);
            $data['requires_attachment'] = (bool) ($request->requires_attachment ?? false);
            $data['requires_approval'] = (bool) ($request->requires_approval ?? true);
            $data['allows_half_day'] = (bool) ($request->allows_half_day ?? false);
            $data['allows_negative_balance'] = (bool) ($request->allows_negative_balance ?? false);
            $data['requires_reason'] = (bool) ($request->requires_reason ?? false);

            $type = LeaveType::create($data);

            HrAuditService::log(
                'leave_type_created',
                'LeaveType',
                $type->id,
                null,
                $type->toArray(),
                "Creación del tipo de ausencia '{$type->name}' ({$type->code})"
            );

            return $type;
        });

        return redirect()->route('rrhh.leave-types.index')
            ->with('ok', "Tipo de ausencia '{$leaveType->name}' creado con éxito.");
    }

    public function edit(LeaveType $leaveType)
    {
        return view('hr.leave_types.edit', compact('leaveType'));
    }

    public function update(LeaveTypeRequest $request, LeaveType $leaveType)
    {
        $oldValues = $leaveType->toArray();

        DB::transaction(function () use ($request, $leaveType, $oldValues) {
            $data = $request->validated();
            $data['is_active'] = $request->has('is_active') ? (bool) $request->is_active : false;
            $data['deducts_from_balance'] = (bool) ($request->deducts_from_balance ?? false);
            $data['requires_attachment'] = (bool) ($request->requires_attachment ?? false);
            $data['requires_approval'] = (bool) ($request->requires_approval ?? true);
            $data['allows_half_day'] = (bool) ($request->allows_half_day ?? false);
            $data['allows_negative_balance'] = (bool) ($request->allows_negative_balance ?? false);
            $data['requires_reason'] = (bool) ($request->requires_reason ?? false);

            $leaveType->update($data);

            HrAuditService::log(
                'leave_type_updated',
                'LeaveType',
                $leaveType->id,
                $oldValues,
                $leaveType->fresh()->toArray(),
                "Actualización del tipo de ausencia '{$leaveType->name}'"
            );
        });

        return redirect()->route('rrhh.leave-types.index')
            ->with('ok', "Tipo de ausencia '{$leaveType->name}' actualizado correctamente.");
    }

    public function destroy(LeaveType $leaveType)
    {
        if ($leaveType->requests()->exists()) {
            return redirect()->route('rrhh.leave-types.index')
                ->with('error', "No es posible eliminar el tipo de ausencia '{$leaveType->name}' porque posee solicitudes registradas. Puede desactivarlo en su lugar.");
        }

        $typeName = $leaveType->name;
        $oldValues = $leaveType->toArray();

        DB::transaction(function () use ($leaveType, $oldValues, $typeName) {
            $leaveType->delete();

            HrAuditService::log(
                'leave_type_deleted',
                'LeaveType',
                $leaveType->id,
                $oldValues,
                null,
                "Eliminación del tipo de ausencia '{$typeName}'"
            );
        });

        return redirect()->route('rrhh.leave-types.index')
            ->with('ok', "Tipo de ausencia '{$typeName}' eliminado correctamente.");
    }

    public function toggleStatus(LeaveType $leaveType)
    {
        $oldStatus = $leaveType->is_active;
        $leaveType->update(['is_active' => !$oldStatus]);

        HrAuditService::log(
            'leave_type_status_toggle',
            'LeaveType',
            $leaveType->id,
            ['is_active' => $oldStatus],
            ['is_active' => $leaveType->is_active],
            "Cambio de estado del tipo de ausencia '{$leaveType->name}' a " . ($leaveType->is_active ? 'Activo' : 'Inactivo')
        );

        return redirect()->back()
            ->with('ok', "Estado del tipo de ausencia '{$leaveType->name}' actualizado a " . ($leaveType->is_active ? 'Activo' : 'Inactivo') . ".");
    }
}
