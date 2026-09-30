<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\HR\Employee;
use App\Models\HR\Holiday;
use App\Models\HR\HrNotification;
use App\Models\HR\LeaveApprovalLog;
use App\Models\HR\LeaveRequest;
use App\Models\HR\LeaveType;
use App\Services\HR\HrAuditService;
use App\Services\HR\HrLeaveCalculationService;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HrManagerController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'terms.accepted', 'hr.access:manager']);
    }

    private function getManagerEmployee(): Employee
    {
        $employee = auth()->user()->hrEmployee;
        if (!$employee || $employee->status !== 'activo') {
            abort(403, 'No posee un perfil activo de colaborador en el sistema.');
        }
        return $employee;
    }

    public function teamRequests(Request $request)
    {
        $manager = $this->getManagerEmployee();
        $teamEmployeeIds = $manager->directReports()->pluck('id')->toArray();

        $query = LeaveRequest::with(['employee.position', 'employee.department', 'leaveType', 'approvedByUser'])
            ->whereIn('employee_id', $teamEmployeeIds);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('employee_id')) {
            $query->where('employee_id', $request->employee_id);
        }

        $requests = $query->orderByRaw("FIELD(status, 'pendiente_manager', 'pendiente_rrhh', 'pendiente', 'aprobada', 'rechazada', 'cancelada')")
            ->latest('date_from')
            ->paginate(15)
            ->withQueryString();

        $teamEmployees = $manager->directReports()->orderBy('last_name')->get();
        $directReports = $teamEmployees;

        $stats = [
            'team_count' => $teamEmployees->count(),
            'pending_count' => LeaveRequest::whereIn('employee_id', $teamEmployeeIds)->whereIn('status', ['pendiente_manager', 'pendiente_rrhh', 'pendiente'])->count(),
            'absent_today_count' => LeaveRequest::whereIn('employee_id', $teamEmployeeIds)
                ->where('status', 'aprobada')
                ->where('date_from', '<=', now()->toDateString())
                ->where('date_to', '>=', now()->toDateString())
                ->count(),
            'pending' => LeaveRequest::whereIn('employee_id', $teamEmployeeIds)->whereIn('status', ['pendiente_manager', 'pendiente_rrhh', 'pendiente'])->count(),
            'approved' => LeaveRequest::whereIn('employee_id', $teamEmployeeIds)->where('status', 'aprobada')->count(),
            'today_absent' => LeaveRequest::whereIn('employee_id', $teamEmployeeIds)
                ->where('status', 'aprobada')
                ->where('date_from', '<=', now()->toDateString())
                ->where('date_to', '>=', now()->toDateString())
                ->count(),
        ];

        return view('hr.manager.team_requests', compact('requests', 'teamEmployees', 'directReports', 'stats', 'manager'));
    }

    public function approveRequest(Request $request, LeaveRequest $leaveRequest, HrLeaveCalculationService $calcService)
    {
        $manager = $this->getManagerEmployee();

        // 1. Verificación de subordinación directa
        if ((int) $leaveRequest->employee->manager_id !== (int) $manager->id) {
            abort(403, 'No tiene autorización para gestionar solicitudes de colaboradores que no pertenecen a su equipo directo.');
        }

        // 2. Un manager NUNCA puede aprobar su propia solicitud
        if ((int) $leaveRequest->employee_id === (int) $manager->id) {
            abort(403, 'Un responsable no puede aprobar su propia solicitud de ausencia.');
        }

        DB::transaction(function () use ($request, $leaveRequest, $manager, $calcService) {
            /** @var LeaveRequest $lockedRequest */
            $lockedRequest = LeaveRequest::where('id', $leaveRequest->id)->lockForUpdate()->first();

            if ($lockedRequest->status === 'aprobada') {
                return; // Idempotencia
            }

            $oldValues = $lockedRequest->toArray();

            // Flujo de 2 niveles: Si la solicitud requiere aprobación final de RR. HH.,
            // el manager la aprueba y pasa a 'pendiente_rrhh' (o si no requiere RR. HH. pasa a 'aprobada').
            $requiresRrhhFinal = true; // Por defecto política de control interno

            $nextStatus = $requiresRrhhFinal ? 'pendiente_rrhh' : 'aprobada';

            $lockedRequest->update([
                'status' => $nextStatus,
                'approved_by' => ($nextStatus === 'aprobada') ? auth()->id() : null,
                'approved_at' => ($nextStatus === 'aprobada') ? now() : null,
            ]);

            LeaveApprovalLog::create([
                'leave_request_id' => $lockedRequest->id,
                'approver_id' => auth()->id(),
                'level' => 'manager',
                'action' => 'aprobada_manager',
                'comment' => $request->comment ?? 'Aprobación otorgada por el Responsable de Equipo',
                'ip_address' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 255),
            ]);

            // Si pasa a aprobada definitiva, mover a used_days.
            // Si pasa a pendiente_rrhh, los días CONTINÚAN como pending_days (comprometidos).
            if ($nextStatus === 'aprobada') {
                $calcService->approveAllocations($lockedRequest);
            }

            HrAuditService::log(
                'aprobar_manager',
                'hr_leave_requests',
                $lockedRequest->id,
                $oldValues,
                $lockedRequest->fresh()->toArray(),
                "Aprobación de responsable para {$lockedRequest->employee->full_name}. Estado resultante: {$nextStatus}"
            );

            // Notificación
            if ($lockedRequest->employee->user_id) {
                HrNotification::create([
                    'user_id' => $lockedRequest->employee->user_id,
                    'title' => 'Solicitud Avalada por Responsable',
                    'message' => "Tu solicitud de {$lockedRequest->leaveType->name} fue aprobada por tu responsable y " . ($nextStatus === 'pendiente_rrhh' ? "enviada a RR. HH. para revisión final." : "aprobada definitivamente."),
                    'type' => 'leave_manager_approved',
                    'data' => ['leave_request_id' => $lockedRequest->id],
                    'is_read' => false,
                ]);
            }
        });

        return redirect()->back()->with('success', 'Solicitud avalada correctamente.');
    }

    public function rejectRequest(Request $request, LeaveRequest $leaveRequest, HrLeaveCalculationService $calcService)
    {
        $manager = $this->getManagerEmployee();

        if ((int) $leaveRequest->employee->manager_id !== (int) $manager->id) {
            abort(403, 'No tiene autorización para gestionar solicitudes ajenas.');
        }

        if ((int) $leaveRequest->employee_id === (int) $manager->id) {
            abort(403, 'Un responsable no puede gestionar su propia solicitud.');
        }

        $request->validate([
            'comments' => 'nullable|string|min:5|max:1000',
            'reason' => 'nullable|string|min:5|max:1000',
        ]);

        $comment = $request->comments ?: $request->reason;
        if (empty(trim((string)$comment))) {
            return redirect()->back()->withErrors(['comments' => 'El motivo de rechazo es obligatorio.'])->withInput();
        }

        DB::transaction(function () use ($comment, $request, $leaveRequest, $calcService) {
            /** @var LeaveRequest $lockedRequest */
            $lockedRequest = LeaveRequest::where('id', $leaveRequest->id)->lockForUpdate()->first();

            if ($lockedRequest->status === 'rechazada') {
                return;
            }

            $wasApproved = ($lockedRequest->status === 'aprobada');
            $oldValues = $lockedRequest->toArray();

            $lockedRequest->update([
                'status' => 'rechazada',
                'rejected_by' => auth()->id(),
                'rejected_at' => now(),
                'rejection_reason' => $comment,
            ]);

            LeaveApprovalLog::create([
                'leave_request_id' => $lockedRequest->id,
                'approver_id' => auth()->id(),
                'level' => 'manager',
                'action' => 'rechazada_manager',
                'comment' => $comment,
                'ip_address' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 255),
            ]);

            $calcService->releaseAllocations($lockedRequest, $wasApproved);

            HrAuditService::log(
                'rechazar_manager',
                'hr_leave_requests',
                $lockedRequest->id,
                $oldValues,
                $lockedRequest->fresh()->toArray(),
                "Rechazo por responsable para {$lockedRequest->employee->full_name}. Motivo: {$comment}"
            );

            if ($lockedRequest->employee->user_id) {
                HrNotification::create([
                    'user_id' => $lockedRequest->employee->user_id,
                    'title' => 'Solicitud de Ausencia Rechazada',
                    'message' => "Tu solicitud de {$lockedRequest->leaveType->name} fue rechazada por tu responsable. Motivo: {$comment}",
                    'type' => 'leave_rejected',
                    'data' => ['leave_request_id' => $lockedRequest->id],
                    'is_read' => false,
                ]);
            }
        });

        return redirect()->back()->with('success', 'Solicitud rechazada y días reservados liberados.');
    }

    public function teamCalendar(Request $request)
    {
        $manager = $this->getManagerEmployee();
        $teamEmployeeIds = $manager->directReports()->pluck('id')->toArray();

        $monthStr = $request->get('month', Carbon::today()->format('Y-m'));
        $currentMonth = Carbon::createFromFormat('Y-m', $monthStr)->startOfMonth();

        $startCalendar = $currentMonth->copy()->startOfWeek(Carbon::MONDAY);
        $endCalendar = $currentMonth->copy()->endOfMonth()->endOfWeek(Carbon::SUNDAY);

        // Ausencias de colaboradores del equipo directo únicamente
        $leaves = LeaveRequest::with(['employee', 'leaveType'])
            ->whereIn('employee_id', $teamEmployeeIds)
            ->whereIn('status', ['aprobada', 'pendiente_manager', 'pendiente_rrhh', 'pendiente'])
            ->where(function ($q) use ($startCalendar, $endCalendar) {
                $q->where('date_from', '<=', $endCalendar->toDateString())
                  ->where('date_to', '>=', $startCalendar->toDateString());
            })
            ->get();

        $holidays = Holiday::where('is_active', true)
            ->where(function ($q) use ($startCalendar, $endCalendar) {
                $q->whereBetween('date', [$startCalendar->toDateString(), $endCalendar->toDateString()])
                  ->orWhere('is_recurring', true);
            })
            ->get();

        $teamEmployees = $manager->directReports()->orderBy('last_name')->get();

        return view('hr.manager.team_calendar', compact('currentMonth', 'startCalendar', 'endCalendar', 'leaves', 'holidays', 'teamEmployees', 'manager'));
    }
}
