<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Http\Requests\HR\AgreementRequest;
use App\Models\HR\Agreement;
use App\Services\HR\HrAuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HrAgreementController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'terms.accepted', 'hr.access:admin']);
    }

    public function index(Request $request)
    {
        $query = Agreement::withCount(['employees as employees_count']);

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

        $agreements = $query->orderBy('name')->paginate(15)->withQueryString();

        return view('hr.agreements.index', compact('agreements'));
    }

    public function create()
    {
        return view('hr.agreements.create');
    }

    public function store(AgreementRequest $request)
    {
        $agreement = DB::transaction(function () use ($request) {
            $data = $request->validated();
            $data['is_active'] = $request->has('is_active') ? (bool) $request->is_active : true;

            $ag = Agreement::create($data);

            HrAuditService::log(
                'agreement_created',
                'Agreement',
                $ag->id,
                null,
                $ag->toArray(),
                "Creación de convenio/política laboral '{$ag->name}' ({$ag->code})"
            );

            return $ag;
        });

        return redirect()->route('rrhh.agreements.index')
            ->with('ok', "Convenio '{$agreement->name}' creado con éxito.");
    }

    public function edit(Agreement $agreement)
    {
        return view('hr.agreements.edit', compact('agreement'));
    }

    public function update(AgreementRequest $request, Agreement $agreement)
    {
        $oldValues = $agreement->toArray();

        DB::transaction(function () use ($request, $agreement, $oldValues) {
            $data = $request->validated();
            $data['is_active'] = $request->has('is_active') ? (bool) $request->is_active : false;

            $agreement->update($data);

            HrAuditService::log(
                'agreement_updated',
                'Agreement',
                $agreement->id,
                $oldValues,
                $agreement->fresh()->toArray(),
                "Actualización de convenio '{$agreement->name}'"
            );
        });

        return redirect()->route('rrhh.agreements.index')
            ->with('ok', "Convenio '{$agreement->name}' actualizado correctamente.");
    }

    public function destroy(Agreement $agreement)
    {
        if ($agreement->employees()->exists()) {
            return redirect()->route('rrhh.agreements.index')
                ->with('error', "No es posible eliminar el convenio '{$agreement->name}' porque posee colaboradores asociados.");
        }

        $agName = $agreement->name;
        $oldValues = $agreement->toArray();

        DB::transaction(function () use ($agreement, $oldValues, $agName) {
            $agreement->delete();

            HrAuditService::log(
                'agreement_deleted',
                'Agreement',
                $agreement->id,
                $oldValues,
                null,
                "Eliminación de convenio '{$agName}'"
            );
        });

        return redirect()->route('rrhh.agreements.index')
            ->with('ok', "Convenio '{$agName}' eliminado correctamente.");
    }

    public function toggleStatus(Agreement $agreement)
    {
        $oldStatus = $agreement->is_active;
        $agreement->update(['is_active' => !$oldStatus]);

        HrAuditService::log(
            'agreement_status_toggle',
            'Agreement',
            $agreement->id,
            ['is_active' => $oldStatus],
            ['is_active' => $agreement->is_active],
            "Cambio de estado de convenio '{$agreement->name}' a " . ($agreement->is_active ? 'Activo' : 'Inactivo')
        );

        return redirect()->back()
            ->with('ok', "Estado de convenio '{$agreement->name}' actualizado a " . ($agreement->is_active ? 'Activo' : 'Inactivo') . ".");
    }
}
