<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\HR\Branch;
use App\Models\HR\Department;
use App\Models\HR\Employee;
use App\Models\HR\HrNotification;
use App\Models\HR\LeaveApprovalLog;
use App\Models\HR\LeaveRequest;
use App\Models\HR\LeaveType;
use App\Services\HR\HrAuditService;
use App\Services\HR\HrLeaveCalculationService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class HrLeaveRequestController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'terms.accepted', 'hr.access:admin']);
    }

    public function index(Request $request)
    {
        $query = LeaveRequest::with(['employee.department', 'employee.position', 'employee.branch', 'leaveType', 'currentApprover', 'approvedByUser', 'rejectedByUser', 'allocations']);

        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->whereHas('employee', function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('dni', 'like', "%{$search}%")
                  ->orWhere('file_number', 'like', "%{$search}%");
            });
        }

        if ($request->filled('employee_id')) {
            $query->where('employee_id', $request->employee_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('leave_type_id')) {
            $query->where('leave_type_id', $request->leave_type_id);
        }

        if ($request->filled('department_id')) {
            $query->whereHas('employee', function ($q) use ($request) {
                $q->where('department_id', $request->department_id);
            });
        }

        if ($request->filled('branch_id')) {
            $query->whereHas('employee', function ($q) use ($request) {
                $q->where('branch_id', $request->branch_id);
            });
        }

        if ($request->filled('date_from')) {
            $query->where('date_from', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->where('date_to', '<=', $request->date_to);
        }

        $requests = $query->orderByRaw("FIELD(status, 'pendiente_manager', 'pendiente_rrhh', 'pendiente', 'borrador', 'aprobada', 'rechazada', 'cancelada', 'finalizada')")
            ->latest('date_from')
            ->paginate(15)
            ->withQueryString();

        $employees = Employee::orderBy('last_name')->get();
        $leaveTypes = LeaveType::orderBy('display_order')->get();
        $departments = Department::where('is_active', true)->orderBy('name')->get();
        $branches = Branch::where('is_active', true)->orderBy('name')->get();

        $stats = [
            'total' => LeaveRequest::count(),
            'pending' => LeaveRequest::whereIn('status', ['pendiente_manager', 'pendiente_rrhh', 'pendiente'])->count(),
            'approved' => LeaveRequest::where('status', 'aprobada')->count(),
            'rejected' => LeaveRequest::where('status', 'rechazada')->count(),
        ];

        return view('hr.leave_requests.index', compact('requests', 'employees', 'leaveTypes', 'departments', 'branches', 'stats') + ['leaveRequests' => $requests]);
    }

    public function create(Request $request, HrLeaveCalculationService $calcService)
    {
        $employees = Employee::with(['department', 'position', 'branch', 'agreement'])
            ->where('status', 'activo')
            ->orderBy('last_name')
            ->get();

        $leaveTypes = LeaveType::where('is_active', true)->orderBy('display_order')->get();

        $selectedEmployeeId = $request->get('employee_id');
        $selectedLeaveTypeId = $request->get('leave_type_id');

        $selectedEmployee = null;
        $balances = [];
        $currentYear = Carbon::today()->year;

        if ($selectedEmployeeId) {
            $selectedEmployee = Employee::with(['department', 'position', 'branch', 'agreement'])->find($selectedEmployeeId);
            if ($selectedEmployee) {
                foreach ($leaveTypes as $type) {
                    $balances[$type->id] = $calcService->getOrCreateBalance($selectedEmployee, $type, $currentYear);
                }
            }
        }

        if (!$selectedLeaveTypeId && $leaveTypes->isNotEmpty()) {
            $vacType = $leaveTypes->firstWhere('code', 'VAC') ?? $leaveTypes->first();
            $selectedLeaveTypeId = $vacType->id;
        }

        return view('hr.leave_requests.create', compact(
            'employees',
            'leaveTypes',
            'selectedEmployeeId',
            'selectedLeaveTypeId',
            'selectedEmployee',
            'balances',
            'currentYear'
        ));
    }

    public function calculatePreview(Request $request, HrLeaveCalculationService $calcService)
    {
        $request->validate([
            'employee_id' => 'required|exists:hr_employees,id',
            'leave_type_id' => 'required|exists:hr_leave_types,id',
            'date_from' => 'required|date',
            'date_to' => 'required|date|after_or_equal:date_from',
            'is_half_day' => 'nullable|boolean',
            'half_day_type' => 'nullable|string|in:manana,tarde',
        ]);

        $employee = Employee::findOrFail($request->employee_id);
        $leaveType = LeaveType::findOrFail($request->leave_type_id);
        $startDate = Carbon::parse($request->date_from);
        $endDate = Carbon::parse($request->date_to);
        $isHalfDay = (bool) $request->is_half_day;
        $halfDayType = $request->half_day_type;

        $overlap = $calcService->findOverlap($employee, $startDate, $endDate, $isHalfDay, $halfDayType);

        $periodsBreakdown = $calcService->calculateDaysByPeriod(
            $startDate,
            $endDate,
            $leaveType,
            $isHalfDay,
            $halfDayType,
            $employee->branch_id
        );

        $totalDays = 0;
        foreach ($periodsBreakdown as $p) {
            $totalDays += (float) $p['days'];
        }

        $currentYear = $startDate->year;
        $balance = $calcService->getOrCreateBalance($employee, $leaveType, $currentYear);

        return response()->json([
            'success' => true,
            'total_computable_days' => $totalDays,
            'periods_breakdown' => $periodsBreakdown,
            'has_overlap' => ($overlap !== null),
            'overlap_details' => $overlap ? "Se superpone con una ausencia registrada (#{$overlap->id} del {$overlap->date_from->format('d/m/Y')} al {$overlap->date_to->format('d/m/Y')} - Estado: " . ucfirst($overlap->status) . ")" : null,
            'balance' => [
                'available_days' => (float) $balance->available_days,
                'total_granted' => (float) $balance->total_granted,
                'used_days' => (float) $balance->used_days,
                'pending_days' => (float) $balance->pending_days,
                'remaining_after' => (float) $balance->available_days - $totalDays,
            ],
            'leave_type' => [
                'name' => $leaveType->name,
                'color' => $leaveType->color,
                'calculation_unit' => $leaveType->calculation_unit,
                'deducts_from_balance' => (bool) $leaveType->deducts_from_balance,
                'allows_negative_balance' => (bool) $leaveType->allows_negative_balance,
            ],
        ]);
    }

    public function store(Request $request, HrLeaveCalculationService $calcService)
    {
        $request->validate([
            'employee_id' => 'required|exists:hr_employees,id',
            'leave_type_id' => 'required|exists:hr_leave_types,id',
            'date_from' => 'required|date',
            'date_to' => 'required|date|after_or_equal:date_from',
            'is_half_day' => 'nullable|boolean',
            'half_day_type' => 'nullable|required_if:is_half_day,1,true|in:manana,tarde',
            'reason' => 'nullable|string|max:1000',
            'confidential_notes' => 'nullable|string|max:1000',
            'auto_approve' => 'nullable|boolean',
            'attachment' => 'nullable|file|max:10240|mimes:pdf,jpg,jpeg,png,doc,docx',
        ], [
            'employee_id.required' => 'Debe seleccionar un colaborador.',
            'leave_type_id.required' => 'Debe seleccionar el tipo de licencia o vacaciones.',
            'date_from.required' => 'La fecha de inicio es requerida.',
            'date_to.required' => 'La fecha de fin es requerida.',
            'date_to.after_or_equal' => 'La fecha de fin debe ser igual o posterior a la fecha de inicio.',
        ]);

        $employee = Employee::findOrFail($request->employee_id);
        $leaveType = LeaveType::findOrFail($request->leave_type_id);
        $startDate = Carbon::parse($request->date_from);
        $endDate = Carbon::parse($request->date_to);
        $isHalfDay = (bool) $request->is_half_day;
        $halfDayType = $request->half_day_type;
        $autoApprove = $request->boolean('auto_approve', true);
        $reason = $request->filled('reason') ? trim($request->reason) : 'Asignación de ' . $leaveType->name . ' por Recursos Humanos';

        try {
            $leaveRequest = DB::transaction(function () use ($request, $employee, $leaveType, $startDate, $endDate, $isHalfDay, $halfDayType, $autoApprove, $reason, $calcService) {
                // Bloqueo de fila de empleado para consistencia
                Employee::where('id', $employee->id)->lockForUpdate()->first();

                // 1. Verificación de superposiciones
                $overlap = $calcService->findOverlap($employee, $startDate, $endDate, $isHalfDay, $halfDayType);
                if ($overlap) {
                    throw new \InvalidArgumentException("El período seleccionado coincide con una ausencia existente (#{$overlap->id} del {$overlap->date_from->format('d/m/Y')} al {$overlap->date_to->format('d/m/Y')} - Estado: " . ucfirst($overlap->status) . ").");
                }

                // 2. Cómputo de días por período / año
                $periodsBreakdown = $calcService->calculateDaysByPeriod(
                    $startDate,
                    $endDate,
                    $leaveType,
                    $isHalfDay,
                    $halfDayType,
                    $employee->branch_id
                );

                $totalDays = 0;
                foreach ($periodsBreakdown as $p) {
                    $totalDays += (float) $p['days'];
                }

                if ($totalDays <= 0) {
                    throw new \InvalidArgumentException("El rango de fechas seleccionado no contiene días computables laborables según el calendario.");
                }

                // 3. Manejo de archivo adjunto seguro
                $attachmentPath = null;
                $originalFilename = null;
                if ($request->hasFile('attachment')) {
                    $file = $request->file('attachment');
                    $originalFilename = $file->getClientOriginalName();
                    $employeeUuid = hash('sha256', 'employee_' . $employee->id);
                    $fileUuid = (string) Str::uuid();
                    $ext = strtolower($file->getClientOriginalExtension() ?: 'pdf');
                    $safeDir = "hr/leave_attachments/{$employeeUuid}";
                    $safeFileName = "{$fileUuid}.{$ext}";
                    $attachmentPath = Storage::disk('local')->putFileAs($safeDir, $file, $safeFileName);
                }

                // 4. Estado inicial
                $initialStatus = $autoApprove ? 'aprobada' : 'pendiente_rrhh';

                $newRequest = LeaveRequest::create([
                    'employee_id' => $employee->id,
                    'leave_type_id' => $leaveType->id,
                    'date_from' => $startDate->toDateString(),
                    'date_to' => $endDate->toDateString(),
                    'days_count' => $totalDays,
                    'is_half_day' => $isHalfDay,
                    'half_day_type' => $halfDayType,
                    'reason' => $reason,
                    'confidential_notes' => $request->confidential_notes,
                    'attachment_path' => $attachmentPath,
                    'attachment_original_name' => $originalFilename,
                    'status' => $initialStatus,
                    'approved_by' => $autoApprove ? auth()->id() : null,
                    'approved_at' => $autoApprove ? now() : null,
                    'idempotency_token' => (string) Str::uuid(),
                ]);

                // 5. Asignación y reserva de saldos
                $calcService->reserveAllocations($newRequest, $employee, $leaveType, $periodsBreakdown);

                // Si fue auto-aprobada, consolidar allocations de inmediato
                if ($autoApprove) {
                    $calcService->approveAllocations($newRequest);

                    LeaveApprovalLog::create([
                        'leave_request_id' => $newRequest->id,
                        'approver_id' => auth()->id(),
                        'level' => 'rrhh',
                        'action' => 'aprobada_rrhh',
                        'comment' => 'Asignación directa y aprobación registrada por Recursos Humanos',
                        'ip_address' => $request->ip(),
                        'user_agent' => substr((string) $request->userAgent(), 0, 255),
                    ]);
                } else {
                    LeaveApprovalLog::create([
                        'leave_request_id' => $newRequest->id,
                        'approver_id' => auth()->id(),
                        'level' => 'rrhh',
                        'action' => 'solicitada',
                        'comment' => 'Solicitud registrada administrativamente por Recursos Humanos',
                        'ip_address' => $request->ip(),
                        'user_agent' => substr((string) $request->userAgent(), 0, 255),
                    ]);
                }

                // 6. Registro de Auditoría
                HrAuditService::log(
                    'asignar_vacaciones',
                    'hr_leave_requests',
                    $newRequest->id,
                    null,
                    $newRequest->toArray(),
                    "Asignación administrativa de {$leaveType->name} ({$totalDays} días) para {$employee->full_name} del {$startDate->format('d/m/Y')} al {$endDate->format('d/m/Y')}" . ($autoApprove ? ' (Aprobación inmediata)' : '')
                );

                // 7. Notificación al colaborador si tiene usuario
                if ($employee->user_id) {
                    $notifTitle = $autoApprove ? 'Vacaciones / Licencia Asignada y Aprobada' : 'Solicitud de Licencia Registrada';
                    $notifMsg = $autoApprove
                        ? "Recursos Humanos te ha otorgado {$leaveType->name} del {$startDate->format('d/m/Y')} al {$endDate->format('d/m/Y')} ({$totalDays} días)."
                        : "Se ha registrado una solicitud de {$leaveType->name} del {$startDate->format('d/m/Y')} al {$endDate->format('d/m/Y')} para tu legajo.";

                    HrNotification::create([
                        'user_id' => $employee->user_id,
                        'title' => $notifTitle,
                        'message' => $notifMsg,
                        'type' => 'leave_assigned',
                        'data' => ['leave_request_id' => $newRequest->id],
                        'is_read' => false,
                    ]);
                }

                return $newRequest;
            });

            return redirect()->route('rrhh.leave-requests.show', $leaveRequest)
                ->with('success', "Se han asignado exitosamente {$leaveRequest->days_count} días de {$leaveType->name} a {$employee->full_name}.");

        } catch (\InvalidArgumentException $e) {
            return redirect()->back()->withErrors(['date_from' => $e->getMessage()])->withInput();
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['date_from' => 'Error al procesar la asignación: ' . $e->getMessage()])->withInput();
        }
    }

    public function show(LeaveRequest $leaveRequest, HrLeaveCalculationService $calcService)
    {
        $leaveRequest->load([
            'employee.department',
            'employee.position',
            'employee.branch',
            'employee.manager',
            'leaveType',
            'currentApprover',
            'approvedByUser',
            'rejectedByUser',
            'approvalLogs.approver',
            'allocations.leaveBalance',
        ]);

        $year = $leaveRequest->date_from->year;
        $balance = $calcService->getOrCreateBalance($leaveRequest->employee, $leaveRequest->leaveType, $year);

        return view('hr.leave_requests.show', compact('leaveRequest', 'balance', 'year'));
    }

    public function approve(Request $request, LeaveRequest $leaveRequest, HrLeaveCalculationService $calcService)
    {
        DB::transaction(function () use ($request, $leaveRequest, $calcService) {
            /** @var LeaveRequest $lockedRequest */
            $lockedRequest = LeaveRequest::where('id', $leaveRequest->id)->lockForUpdate()->first();

            if ($lockedRequest->status === 'aprobada') {
                return; // Idempotencia: evitar doble aprobación
            }

            $oldValues = $lockedRequest->toArray();

            $lockedRequest->update([
                'status' => 'aprobada',
                'approved_by' => auth()->id(),
                'approved_at' => now(),
                'current_approver_id' => null,
            ]);

            // Registrar log de aprobación RR. HH.
            LeaveApprovalLog::create([
                'leave_request_id' => $lockedRequest->id,
                'approver_id' => auth()->id(),
                'level' => 'rrhh',
                'action' => 'aprobada_rrhh',
                'comment' => $request->comment ?? 'Aprobación definitiva otorgada por Recursos Humanos',
                'ip_address' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 255),
            ]);

            // Mover allocations de pending a used
            $calcService->approveAllocations($lockedRequest);

            // Auditoría
            HrAuditService::log(
                'aprobar',
                'hr_leave_requests',
                $lockedRequest->id,
                $oldValues,
                $lockedRequest->fresh()->toArray(),
                "Aprobación definitiva de solicitud de {$lockedRequest->leaveType->name} para {$lockedRequest->employee->full_name}"
            );

            // Notificación al empleado
            if ($lockedRequest->employee->user_id) {
                HrNotification::create([
                    'user_id' => $lockedRequest->employee->user_id,
                    'title' => 'Solicitud de Ausencia Aprobada',
                    'message' => "Tu solicitud de {$lockedRequest->leaveType->name} del {$lockedRequest->date_from->format('d/m/Y')} al {$lockedRequest->date_to->format('d/m/Y')} ha sido aprobada.",
                    'type' => 'leave_approved',
                    'data' => ['leave_request_id' => $lockedRequest->id],
                    'is_read' => false,
                ]);
            }
        });

        return redirect()->back()->with('success', 'Solicitud aprobada exitosamente y saldo actualizado.');
    }

    public function reject(Request $request, LeaveRequest $leaveRequest, HrLeaveCalculationService $calcService)
    {
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
                return; // Idempotencia
            }

            $wasApproved = ($lockedRequest->status === 'aprobada');
            $oldValues = $lockedRequest->toArray();

            $lockedRequest->update([
                'status' => 'rechazada',
                'rejected_by' => auth()->id(),
                'rejected_at' => now(),
                'rejection_reason' => $comment,
                'current_approver_id' => null,
            ]);

            LeaveApprovalLog::create([
                'leave_request_id' => $lockedRequest->id,
                'approver_id' => auth()->id(),
                'level' => 'rrhh',
                'action' => 'rechazada_rrhh',
                'comment' => $comment,
                'ip_address' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 255),
            ]);

            // Liberar allocations
            $calcService->releaseAllocations($lockedRequest, $wasApproved);

            HrAuditService::log(
                'rechazar',
                'hr_leave_requests',
                $lockedRequest->id,
                $oldValues,
                $lockedRequest->fresh()->toArray(),
                "Rechazo de solicitud de {$lockedRequest->leaveType->name} para {$lockedRequest->employee->full_name}. Motivo: {$comment}"
            );

            if ($lockedRequest->employee->user_id) {
                HrNotification::create([
                    'user_id' => $lockedRequest->employee->user_id,
                    'title' => 'Solicitud de Ausencia Rechazada',
                    'message' => "Tu solicitud de {$lockedRequest->leaveType->name} ha sido rechazada por RR. HH. Motivo: {$comment}",
                    'type' => 'leave_rejected',
                    'data' => ['leave_request_id' => $lockedRequest->id],
                    'is_read' => false,
                ]);
            }
        });

        return redirect()->back()->with('success', 'Solicitud rechazada y días reservados liberados.');
    }

    public function cancel(Request $request, LeaveRequest $leaveRequest, HrLeaveCalculationService $calcService)
    {
        $comment = $request->comments ?: $request->reason ?: 'Cancelación administrativa por Recursos Humanos';

        DB::transaction(function () use ($comment, $request, $leaveRequest, $calcService) {
            /** @var LeaveRequest $lockedRequest */
            $lockedRequest = LeaveRequest::where('id', $leaveRequest->id)->lockForUpdate()->first();

            if ($lockedRequest->status === 'cancelada') {
                return; // Idempotencia
            }

            $wasApproved = ($lockedRequest->status === 'aprobada');
            $oldValues = $lockedRequest->toArray();

            $lockedRequest->update([
                'status' => 'cancelada',
                'current_approver_id' => null,
            ]);

            LeaveApprovalLog::create([
                'leave_request_id' => $lockedRequest->id,
                'approver_id' => auth()->id(),
                'level' => 'rrhh',
                'action' => 'cancelada_rrhh',
                'comment' => $comment,
                'ip_address' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 255),
            ]);

            // Liberar cupo
            $calcService->releaseAllocations($lockedRequest, $wasApproved);

            HrAuditService::log(
                'cancelar',
                'hr_leave_requests',
                $lockedRequest->id,
                $oldValues,
                $lockedRequest->fresh()->toArray(),
                "Cancelación administrativa de solicitud de {$lockedRequest->leaveType->name} para {$lockedRequest->employee->full_name}"
            );
        });

        return redirect()->back()->with('success', 'Solicitud cancelada administrativamente y saldo devuelto.');
    }

    public function downloadAttachment(LeaveRequest $leaveRequest)
    {
        if (!$leaveRequest->attachment_path || !Storage::disk('local')->exists($leaveRequest->attachment_path)) {
            abort(404, 'El archivo adjunto no existe o fue eliminado.');
        }

        HrAuditService::log(
            'descargar_comprobante',
            'hr_leave_requests',
            $leaveRequest->id,
            null,
            ['file' => $leaveRequest->attachment_name],
            "Descarga de comprobante privado de solicitud #{$leaveRequest->id}"
        );

        $downloadName = $leaveRequest->attachment_original_name ?: 'comprobante_ausencia.pdf';

        return Storage::disk('local')->download($leaveRequest->attachment_path, $downloadName, [
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-transform',
        ]);
    }

    public function previewAttachment(LeaveRequest $leaveRequest)
    {
        if (!$leaveRequest->attachment_path || !Storage::disk('local')->exists($leaveRequest->attachment_path)) {
            abort(404, 'El archivo adjunto no existe o fue eliminado.');
        }

        $content = Storage::disk('local')->get($leaveRequest->attachment_path);
        $mime = Storage::disk('local')->mimeType($leaveRequest->attachment_path) ?: 'application/pdf';

        return response($content, 200, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline; filename="' . ($leaveRequest->attachment_original_name ?: 'preview.pdf') . '"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-transform',
        ]);
    }

    public function reports(Request $request)
    {
        $year = $request->get('year', Carbon::today()->year);

        $absencesByType = DB::table('hr_leave_requests')
            ->join('hr_leave_types', 'hr_leave_requests.leave_type_id', '=', 'hr_leave_types.id')
            ->where('hr_leave_requests.status', 'aprobada')
            ->whereYear('hr_leave_requests.date_from', $year)
            ->select('hr_leave_types.name', 'hr_leave_types.color', DB::raw('SUM(hr_leave_requests.days_count) as total_days'), DB::raw('COUNT(hr_leave_requests.id) as total_requests'))
            ->groupBy('hr_leave_types.name', 'hr_leave_types.color')
            ->get();

        $absencesByDepartment = DB::table('hr_leave_requests')
            ->join('hr_employees', 'hr_leave_requests.employee_id', '=', 'hr_employees.id')
            ->join('hr_departments', 'hr_employees.department_id', '=', 'hr_departments.id')
            ->where('hr_leave_requests.status', 'aprobada')
            ->whereYear('hr_leave_requests.date_from', $year)
            ->select('hr_departments.name', DB::raw('SUM(hr_leave_requests.days_count) as total_days'))
            ->groupBy('hr_departments.name')
            ->get();

        return view('hr.leave_requests.reports', compact('year', 'absencesByType', 'absencesByDepartment'));
    }

    public function exportCsv(Request $request)
    {
        $requests = LeaveRequest::with(['employee.department', 'leaveType', 'approvedByUser'])
            ->latest('date_from')
            ->get();

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="solicitudes_ausencias_' . date('Ymd_His') . '.csv"',
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        $callback = function () use ($requests) {
            $file = fopen('php://output', 'w');
            // BOM UTF-8 para Excel
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));

            $sanitize = function ($val) {
                if ($val === null) return '';
                $str = (string) $val;
                if (strlen($str) > 0 && in_array($str[0], ['=', '+', '-', '@', "\t", "\r"])) {
                    return "'" . $str;
                }
                return $str;
            };

            fputcsv($file, [
                'ID Solicitud',
                'Legajo',
                'Colaborador',
                'DNI',
                'Departamento',
                'Tipo de Ausencia',
                'Desde',
                'Hasta',
                'Días Computados',
                'Medio Día',
                'Estado',
                'Aprobado Por',
                'Fecha Solicitud',
            ], ';');

            foreach ($requests as $r) {
                fputcsv($file, [
                    $r->id,
                    $sanitize($r->employee->file_number ?? '-'),
                    $sanitize($r->employee->full_name ?? '-'),
                    $sanitize($r->employee->dni ?? '-'),
                    $sanitize($r->employee->department->name ?? '-'),
                    $sanitize($r->leaveType->name ?? '-'),
                    $r->date_from->format('d/m/Y'),
                    $r->date_to->format('d/m/Y'),
                    $r->days_count,
                    $r->is_half_day ? 'Sí (' . $r->half_day_type . ')' : 'No',
                    ucfirst(str_replace('_', ' ', $r->status)),
                    $sanitize($r->approvedByUser->name ?? '-'),
                    $r->created_at->format('d/m/Y H:i'),
                ], ';');
            }

            fclose($file);
        };

        return Response::stream($callback, 200, $headers);
    }
}
