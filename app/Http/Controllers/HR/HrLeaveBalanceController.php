<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Http\Requests\HR\LeaveBalanceAdjustmentRequest;
use App\Models\HR\Department;
use App\Models\HR\Employee;
use App\Models\HR\HrNotification;
use App\Models\HR\LeaveBalance;
use App\Models\HR\LeaveBalanceAdjustment;
use App\Models\HR\LeaveType;
use App\Services\HR\HrAuditService;
use App\Services\HR\HrLeaveCalculationService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HrLeaveBalanceController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'terms.accepted', 'hr.access:admin']);
    }

    public function index(Request $request, HrLeaveCalculationService $calcService)
    {
        $year = (int) $request->get('year', Carbon::today()->year);

        $employees = Employee::where('status', 'activo')->orderBy('last_name')->get();
        $leaveTypes = LeaveType::where('is_active', true)->orderBy('display_order')->get();
        $departments = Department::where('is_active', true)->orderBy('name')->get();

        // Inicializar saldos faltantes
        foreach ($employees as $employee) {
            foreach ($leaveTypes as $type) {
                $calcService->getOrCreateBalance($employee, $type, $year);
            }
        }

        $balancesQuery = LeaveBalance::with(['employee.department', 'leaveType'])
            ->where('period_year', $year);

        if ($request->filled('employee_id')) {
            $balancesQuery->where('employee_id', $request->employee_id);
        }

        if ($request->filled('leave_type_id')) {
            $balancesQuery->where('leave_type_id', $request->leave_type_id);
        }

        if ($request->filled('department_id')) {
            $balancesQuery->whereHas('employee', function ($q) use ($request) {
                $q->where('department_id', $request->department_id);
            });
        }

        $balances = $balancesQuery->paginate(20)->withQueryString();

        return view('hr.leave_balances.index', compact('employees', 'leaveTypes', 'departments', 'balances', 'year'));
    }

    public function show(LeaveBalance $balance)
    {
        $balance->load(['employee.department', 'employee.position', 'leaveType', 'leavePolicy', 'adjustments.user', 'allocations.leaveRequest']);

        return view('hr.leave_balances.show', compact('balance'));
    }

    public function adjust(LeaveBalanceAdjustmentRequest $request, LeaveBalance $balance)
    {
        $validated = $request->validated();
        $days = (float) $validated['days'];
        $type = $validated['type']; // positive o negative
        $reason = $validated['reason'];

        $difference = ($type === 'positive') ? $days : -$days;

        try {
            DB::transaction(function () use ($balance, $difference, $reason) {
                /** @var LeaveBalance $lockedBalance */
                $lockedBalance = LeaveBalance::where('id', $balance->id)->lockForUpdate()->first();

                $previousAvailable = (float) $lockedBalance->available_days;
                $newAvailable = $previousAvailable + $difference;

                if ($lockedBalance->is_closed) {
                    throw new \InvalidArgumentException("No es posible realizar ajustes: el período {$lockedBalance->period_year} se encuentra cerrado administrativamente.");
                }

                // Validación de saldo negativo
                if (!$lockedBalance->leaveType->allows_negative_balance && $newAvailable < 0) {
                    throw new \InvalidArgumentException("El ajuste dejaría un saldo negativo ({$newAvailable} días), lo cual no está permitido para {$lockedBalance->leaveType->name}.");
                }

                $oldValues = $lockedBalance->toArray();

                // Registrar ajuste manual en el Registro de Auditoría y tabla de ajustes
                $adjustment = LeaveBalanceAdjustment::create([
                    'employee_id' => $lockedBalance->employee_id,
                    'leave_type_id' => $lockedBalance->leave_type_id,
                    'period_year' => $lockedBalance->period_year,
                    'previous_balance' => $previousAvailable,
                    'new_balance' => $newAvailable,
                    'difference' => $difference,
                    'reason' => $reason,
                    'user_id' => auth()->id(),
                ]);

                // Actualizar adjustment_days consolidado y recalcular available_days
                $lockedBalance->adjustment_days = (float) $lockedBalance->adjustment_days + $difference;
                $lockedBalance->available_days = $lockedBalance->total_granted - ((float) $lockedBalance->used_days + (float) $lockedBalance->pending_days);
                $lockedBalance->save();

                HrAuditService::log(
                    'ajuste_saldo',
                    'hr_leave_balances',
                    $lockedBalance->id,
                    $oldValues,
                    $lockedBalance->fresh()->toArray(),
                    "Ajuste manual de saldo ({$difference} días) para {$lockedBalance->employee->full_name} en {$lockedBalance->leaveType->name} (Año {$lockedBalance->period_year}). Motivo: {$reason}"
                );

                // Notificación al empleado
                if ($lockedBalance->employee->user_id) {
                    HrNotification::create([
                        'user_id' => $lockedBalance->employee->user_id,
                        'title' => 'Ajuste de Saldo de Ausencia',
                        'message' => "Se ha realizado un ajuste manual de " . ($difference > 0 ? "+{$difference}" : "{$difference}") . " días en tu saldo de {$lockedBalance->leaveType->name} ({$lockedBalance->period_year}). Motivo: {$reason}",
                        'type' => 'balance_adjusted',
                        'data' => ['balance_id' => $lockedBalance->id, 'adjustment_id' => $adjustment->id],
                        'is_read' => false,
                    ]);
                }
            });
        } catch (\InvalidArgumentException $e) {
            return redirect()->back()->withErrors(['days' => $e->getMessage()])->withInput();
        }

        return redirect()->back()->with('success', 'Ajuste de saldo registrado y auditado correctamente.');
    }
}
