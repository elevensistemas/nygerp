<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Http\Requests\HR\LeavePolicyRequest;
use App\Models\HR\Agreement;
use App\Models\HR\LeavePolicy;
use App\Models\HR\LeaveType;
use App\Services\HR\HrAuditService;
use Illuminate\Http\Request;

class HrLeavePolicyController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'terms.accepted', 'hr.access:admin']);
    }

    public function index(Request $request)
    {
        $query = LeavePolicy::with(['leaveType', 'agreement']);

        if ($request->filled('leave_type_id')) {
            $query->where('leave_type_id', $request->leave_type_id);
        }

        if ($request->filled('agreement_id')) {
            $query->where('agreement_id', $request->agreement_id);
        }

        $policies = $query->orderBy('leave_type_id')
            ->orderBy('seniority_years_from')
            ->paginate(15)
            ->withQueryString();

        $leaveTypes = LeaveType::orderBy('name')->get();
        $agreements = Agreement::where('is_active', true)->orderBy('name')->get();

        return view('hr.leave_policies.index', compact('policies', 'leaveTypes', 'agreements'));
    }

    public function create()
    {
        $leaveTypes = LeaveType::where('is_active', true)->orderBy('name')->get();
        $agreements = Agreement::where('is_active', true)->orderBy('name')->get();

        return view('hr.leave_policies.create', compact('leaveTypes', 'agreements'));
    }

    public function store(LeavePolicyRequest $request)
    {
        $validated = $request->validated();

        $from = (int) ($validated['seniority_years_from'] ?? $validated['min_seniority_years'] ?? 0);
        $to = isset($validated['seniority_years_to']) ? ($validated['seniority_years_to'] !== '' ? (int)$validated['seniority_years_to'] : null) : (isset($validated['max_seniority_years']) && $validated['max_seniority_years'] !== '' ? (int)$validated['max_seniority_years'] : null);
        $agreementId = !empty($validated['agreement_id']) ? (int)$validated['agreement_id'] : null;

        if ($to !== null && $to < $from) {
            return redirect()->back()->withErrors(['seniority_years_to' => 'La antigüedad máxima debe ser igual o superior a la mínima.'])->withInput();
        }

        // Validación anti-superposición de políticas
        if (LeavePolicy::hasOverlappingPolicy((int)$validated['leave_type_id'], $agreementId, $from, $to)) {
            return redirect()->back()->withErrors(['seniority_years_from' => 'Ya existe una política activa para este tipo de ausencia y convenio con un rango de antigüedad que se superpone.'])->withInput();
        }

        $policy = LeavePolicy::create([
            'leave_type_id' => $validated['leave_type_id'],
            'agreement_id' => $agreementId,
            'name' => $validated['name'] ?? null,
            'seniority_years_from' => $from,
            'seniority_years_to' => $to,
            'days_granted' => $validated['days_granted'],
            'allow_transfer' => (bool) ($validated['allow_transfer'] ?? $validated['allows_carryover'] ?? true),
            'max_transferred_days' => $validated['max_transferred_days'] ?? $validated['max_carryover_days'] ?? null,
            'transfer_expiration_months' => $validated['transfer_expiration_months'] ?? $validated['expiration_months'] ?? 6,
            'is_active' => (bool) ($validated['is_active'] ?? true),
        ]);

        HrAuditService::log(
            'crear',
            'hr_leave_policies',
            $policy->id,
            null,
            $policy->toArray(),
            "Creación de política de licencias #{$policy->id} ({$policy->leaveType->name})"
        );

        return redirect()->route('rrhh.leave-policies.index')->with('success', 'Política de licencia creada exitosamente.');
    }

    public function edit(LeavePolicy $leavePolicy)
    {
        $leaveTypes = LeaveType::where('is_active', true)->orderBy('name')->get();
        $agreements = Agreement::where('is_active', true)->orderBy('name')->get();

        return view('hr.leave_policies.edit', compact('leavePolicy', 'leaveTypes', 'agreements'));
    }

    public function update(LeavePolicyRequest $request, LeavePolicy $leavePolicy)
    {
        $validated = $request->validated();

        $from = (int) ($validated['seniority_years_from'] ?? $validated['min_seniority_years'] ?? 0);
        $to = isset($validated['seniority_years_to']) ? ($validated['seniority_years_to'] !== '' ? (int)$validated['seniority_years_to'] : null) : (isset($validated['max_seniority_years']) && $validated['max_seniority_years'] !== '' ? (int)$validated['max_seniority_years'] : null);
        $agreementId = !empty($validated['agreement_id']) ? (int)$validated['agreement_id'] : null;

        if ($to !== null && $to < $from) {
            return redirect()->back()->withErrors(['seniority_years_to' => 'La antigüedad máxima debe ser igual o superior a la mínima.'])->withInput();
        }

        // Validación anti-superposición
        if (LeavePolicy::hasOverlappingPolicy((int)$validated['leave_type_id'], $agreementId, $from, $to, $leavePolicy->id)) {
            return redirect()->back()->withErrors(['seniority_years_from' => 'Ya existe otra política activa para este tipo de ausencia y convenio con un rango de antigüedad superpuesto.'])->withInput();
        }

        $oldValues = $leavePolicy->toArray();

        $leavePolicy->update([
            'leave_type_id' => $validated['leave_type_id'],
            'agreement_id' => $agreementId,
            'name' => $validated['name'] ?? null,
            'seniority_years_from' => $from,
            'seniority_years_to' => $to,
            'days_granted' => $validated['days_granted'],
            'allow_transfer' => (bool) ($validated['allow_transfer'] ?? $validated['allows_carryover'] ?? true),
            'max_transferred_days' => $validated['max_transferred_days'] ?? $validated['max_carryover_days'] ?? null,
            'transfer_expiration_months' => $validated['transfer_expiration_months'] ?? $validated['expiration_months'] ?? 6,
            'is_active' => (bool) ($validated['is_active'] ?? true),
        ]);

        HrAuditService::log(
            'actualizar',
            'hr_leave_policies',
            $leavePolicy->id,
            $oldValues,
            $leavePolicy->fresh()->toArray(),
            "Actualización de política de licencias #{$leavePolicy->id}"
        );

        return redirect()->route('rrhh.leave-policies.index')->with('success', 'Política actualizada exitosamente.');
    }

    public function destroy(LeavePolicy $leavePolicy)
    {
        $oldValues = $leavePolicy->toArray();
        $leavePolicy->delete();

        HrAuditService::log(
            'eliminar',
            'hr_leave_policies',
            $leavePolicy->id,
            $oldValues,
            null,
            "Eliminación de política de licencias #{$leavePolicy->id}"
        );

        return redirect()->route('rrhh.leave-policies.index')->with('success', 'Política eliminada correctamente.');
    }
}
