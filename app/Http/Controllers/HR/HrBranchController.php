<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Http\Requests\HR\BranchRequest;
use App\Models\HR\Branch;
use App\Services\HR\HrAuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HrBranchController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'terms.accepted', 'hr.access:admin']);
    }

    public function index(Request $request)
    {
        $query = Branch::withCount(['employees as employees_count']);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%")
                  ->orWhere('city', 'like', "%{$search}%")
                  ->orWhere('province', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('is_active', $request->status === 'active');
        }

        $branches = $query->orderBy('name')->paginate(15)->withQueryString();

        return view('hr.branches.index', compact('branches'));
    }

    public function create()
    {
        return view('hr.branches.create');
    }

    public function store(BranchRequest $request)
    {
        $branch = DB::transaction(function () use ($request) {
            $data = $request->validated();
            $data['is_active'] = $request->has('is_active') ? (bool) $request->is_active : true;

            $br = Branch::create($data);

            HrAuditService::log(
                'branch_created',
                'Branch',
                $br->id,
                null,
                $br->toArray(),
                "Creación de la sucursal/base '{$br->name}' ({$br->code})"
            );

            return $br;
        });

        return redirect()->route('rrhh.branches.index')
            ->with('ok', "Sucursal '{$branch->name}' creada con éxito.");
    }

    public function edit(Branch $branch)
    {
        return view('hr.branches.edit', compact('branch'));
    }

    public function update(BranchRequest $request, Branch $branch)
    {
        $oldValues = $branch->toArray();

        DB::transaction(function () use ($request, $branch, $oldValues) {
            $data = $request->validated();
            $data['is_active'] = $request->has('is_active') ? (bool) $request->is_active : false;

            $branch->update($data);

            HrAuditService::log(
                'branch_updated',
                'Branch',
                $branch->id,
                $oldValues,
                $branch->fresh()->toArray(),
                "Actualización de la sucursal '{$branch->name}'"
            );
        });

        return redirect()->route('rrhh.branches.index')
            ->with('ok', "Sucursal '{$branch->name}' actualizada correctamente.");
    }

    public function destroy(Branch $branch)
    {
        if ($branch->employees()->exists()) {
            return redirect()->route('rrhh.branches.index')
                ->with('error', "No es posible eliminar la sucursal '{$branch->name}' porque posee colaboradores asignados.");
        }

        $branchName = $branch->name;
        $oldValues = $branch->toArray();

        DB::transaction(function () use ($branch, $oldValues, $branchName) {
            $branch->delete();

            HrAuditService::log(
                'branch_deleted',
                'Branch',
                $branch->id,
                $oldValues,
                null,
                "Eliminación de la sucursal '{$branchName}'"
            );
        });

        return redirect()->route('rrhh.branches.index')
            ->with('ok', "Sucursal '{$branchName}' eliminada correctamente.");
    }

    public function toggleStatus(Branch $branch)
    {
        $oldStatus = $branch->is_active;
        $branch->update(['is_active' => !$oldStatus]);

        HrAuditService::log(
            'branch_status_toggle',
            'Branch',
            $branch->id,
            ['is_active' => $oldStatus],
            ['is_active' => $branch->is_active],
            "Cambio de estado de sucursal '{$branch->name}' a " . ($branch->is_active ? 'Activa' : 'Inactiva')
        );

        return redirect()->back()
            ->with('ok', "Estado de sucursal '{$branch->name}' actualizado a " . ($branch->is_active ? 'Activa' : 'Inactiva') . ".");
    }
}
