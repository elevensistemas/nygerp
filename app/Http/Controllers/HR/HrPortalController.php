<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Http\Requests\HR\LeaveRequestStoreRequest;
use App\Models\HR\Employee;
use App\Models\HR\Holiday;
use App\Models\HR\HrNotification;
use App\Models\HR\LeaveApprovalLog;
use App\Models\HR\LeaveBalance;
use App\Models\HR\LeaveRequest;
use App\Models\HR\LeaveType;
use App\Models\User;
use App\Services\HR\HrAuditService;
use App\Services\HR\HrLeaveCalculationService;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class HrPortalController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'terms.accepted', 'hr.access:employee']);
    }

    private function getActiveEmployee(): Employee
    {
        $employee = auth()->user()->hrEmployee;
        if (!$employee || $employee->status !== 'activo') {
            abort(403, 'No posee un perfil activo de colaborador en el sistema para realizar esta acción.');
        }
        return $employee;
    }

    public function dashboard(HrLeaveCalculationService $calcService)
    {
        $employee = $this->getActiveEmployee();
        $year = Carbon::today()->year;

        // 1. Saldos del colaborador para el año en curso
        $leaveTypes = LeaveType::where('is_active', true)->orderBy('display_order')->get();
        $balances = [];

        foreach ($leaveTypes as $type) {
            $balances[] = $calcService->getOrCreateBalance($employee, $type, $year);
        }

        // 2. Solicitudes recientes
        $recentRequests = LeaveRequest::with('leaveType')
            ->where('employee_id', $employee->id)
            ->latest('created_at')
            ->take(5)
            ->get();

        // 3. Próxima ausencia programada
        $upcomingLeave = LeaveRequest::with('leaveType')
            ->where('employee_id', $employee->id)
            ->where('status', 'aprobada')
            ->where('date_from', '>=', now()->toDateString())
            ->orderBy('date_from')
            ->first();

        // 4. Próximos feriados
        $upcomingHolidays = Holiday::where('is_active', true)
            ->where(function($q) {
                $q->where('date', '>=', now()->toDateString())
                  ->orWhere('is_recurring', true);
            })
            ->orderBy('date')
            ->take(5)
            ->get();

        $nextLeave = $upcomingLeave;

        // 5. Notificaciones no leídas
        $unreadNotifications = HrNotification::where('user_id', auth()->id())
            ->where('is_read', false)
            ->latest()
            ->take(5)
            ->get();

        return view('hr.portal.dashboard', compact('employee', 'balances', 'recentRequests', 'nextLeave', 'upcomingLeave', 'upcomingHolidays', 'unreadNotifications', 'year'));
    }

    public function myRequests(Request $request)
    {
        $employee = $this->getActiveEmployee();

        $query = LeaveRequest::with(['leaveType', 'currentApprover', 'approvedByUser', 'rejectedByUser', 'allocations'])
            ->where('employee_id', $employee->id);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('year')) {
            $query->whereYear('date_from', $request->year);
        }

        $requests = $query->latest('date_from')->paginate(10)->withQueryString();

        $leaveTypes = LeaveType::where('is_active', true)->orderBy('display_order')->get();

        return view('hr.portal.my_requests', compact('requests', 'leaveTypes', 'employee'));
    }

    public function createRequest(HrLeaveCalculationService $calcService)
    {
        $employee = $this->getActiveEmployee();
        $year = Carbon::today()->year;

        $leaveTypes = LeaveType::where('is_active', true)->orderBy('display_order')->get();

        $balances = [];
        foreach ($leaveTypes as $type) {
            $balances[$type->id] = $calcService->getOrCreateBalance($employee, $type, $year);
        }

        return view('hr.portal.create_request', compact('employee', 'leaveTypes', 'balances', 'year'));
    }

    /**
     * Endpoint AJAX seguro para previsualizar cómputo de días (recalculado en vivo).
     */
    public function calculatePreview(Request $request, HrLeaveCalculationService $calcService)
    {
        $employee = $this->getActiveEmployee();

        $request->validate([
            'leave_type_id' => 'required|exists:hr_leave_types,id',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
            'is_half_day' => 'nullable|boolean',
            'half_day_type' => 'nullable|string|in:manana,tarde',
        ]);

        $startDate = $request->input('start_date') ?: $request->input('date_from');
        $endDate = $request->input('end_date') ?: $request->input('date_to') ?: $startDate;

        if (!$startDate) {
            return response()->json(['success' => false, 'message' => 'Fecha inicial requerida'], 422);
        }

        $leaveType = LeaveType::findOrFail($request->leave_type_id);
        $isHalfDay = (bool) $request->is_half_day;
        $halfDayType = $request->half_day_type;

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

        $overlap = $calcService->findOverlap($employee, $startDate, $endDate, $isHalfDay, $halfDayType);

        return response()->json([
            'success' => true,
            'total_computable_days' => $totalDays,
            'periods_breakdown' => $periodsBreakdown,
            'has_overlap' => ($overlap !== null),
            'overlap_details' => $overlap ? "Se superpone con una solicitud del {$overlap->date_from->format('d/m/Y')} al {$overlap->date_to->format('d/m/Y')} ({$overlap->status})" : null,
        ]);
    }

    public function storeRequest(LeaveRequestStoreRequest $request, HrLeaveCalculationService $calcService)
    {
        $employee = $this->getActiveEmployee();
        $validated = $request->validated();

        $leaveType = LeaveType::findOrFail($validated['leave_type_id']);
        $isHalfDay = (bool) ($validated['is_half_day'] ?? false);
        $halfDayType = $validated['half_day_type'] ?? null;
        $startDate = Carbon::parse($validated['start_date'] ?? $validated['date_from'] ?? $request->input('start_date') ?? $request->input('date_from'));
        $endDate = Carbon::parse($validated['end_date'] ?? $validated['date_to'] ?? $request->input('end_date') ?? $request->input('date_to'));

        // Idempotency token check
        $idempotencyToken = $request->input('idempotency_token') ?: (string) Str::uuid();
        if (LeaveRequest::where('idempotency_token', $idempotencyToken)->exists()) {
            return redirect()->route('rrhh.portal.requests')->with('info', 'La solicitud ya fue registrada previamente.');
        }

        // 1. Anticipación mínima
        $minDays = (int) $leaveType->min_anticipation_days;
        if ($minDays > 0) {
            $minAllowedDate = Carbon::today()->addDays($minDays)->startOfDay();
            if ($startDate->copy()->startOfDay()->lt($minAllowedDate)) {
                return redirect()->back()->withErrors([
                    'start_date' => "Este tipo de ausencia requiere un mínimo de {$minDays} días de anticipación (a partir de {$minAllowedDate->format('d/m/Y')}).",
                    'date_from' => "Este tipo de ausencia requiere un mínimo de {$minDays} días de anticipación (a partir de {$minAllowedDate->format('d/m/Y')}).",
                ])->withInput();
            }
        }

        // Ejecución transaccional pesimista con bloqueo de empleado y saldos
        try {
            $leaveRequest = DB::transaction(function () use ($validated, $request, $employee, $leaveType, $startDate, $endDate, $isHalfDay, $halfDayType, $idempotencyToken, $calcService) {
                // Bloqueo de fila de empleado para evitar carreras
                Employee::where('id', $employee->id)->lockForUpdate()->first();

                // 2. Superposición
                $overlap = $calcService->findOverlap($employee, $startDate, $endDate, $isHalfDay, $halfDayType);
                if ($overlap) {
                    throw new \InvalidArgumentException("El período solicitado coincide o se superpone con una ausencia existente (#{$overlap->id} del {$overlap->date_from->format('d/m/Y')} al {$overlap->date_to->format('d/m/Y')}).");
                }

                // 3. Cómputo por períodos / años
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
                    throw new \InvalidArgumentException("El rango de fechas seleccionado no contiene días computables laborables.");
                }

                // 4. Duración máxima si aplica
                if ($leaveType->max_days_limit && $totalDays > (float) $leaveType->max_days_limit) {
                    throw new \InvalidArgumentException("La cantidad de días solicitados ({$totalDays}) supera el máximo permitido de {$leaveType->max_days_limit} días para este tipo de ausencia.");
                }

                // 5. Manejo seguro de comprobante médico / adjunto
                $attachmentPath = null;
                $originalFilename = null;

                if ($request->hasFile('attachment')) {
                    $file = $request->file('attachment');
                    $originalFilename = $file->getClientOriginalName();

                    // Ruta física segura sin PII: storage/app/hr/leave_attachments/{employee_uuid}/{file_uuid}.{ext}
                    $employeeUuid = hash('sha256', 'employee_' . $employee->id);
                    $fileUuid = (string) Str::uuid();
                    $ext = strtolower($file->getClientOriginalExtension() ?: 'pdf');

                    $safeDir = "hr/leave_attachments/{$employeeUuid}";
                    $safeFileName = "{$fileUuid}.{$ext}";

                    $attachmentPath = Storage::disk('local')->putFileAs($safeDir, $file, $safeFileName);
                }

                // 6. Determinación de estado inicial según workflow
                // Si tiene manager directo y requiere aprobación: 'pendiente_manager'
                // Si no tiene manager directo: 'pendiente_rrhh'
                // Si no requiere aprobación: 'aprobada'
                $initialStatus = 'pendiente_rrhh';
                if ($employee->manager_id && $leaveType->requires_approval) {
                    $initialStatus = 'pendiente_manager';
                } elseif (!$leaveType->requires_approval) {
                    $initialStatus = 'aprobada';
                }

                $newRequest = LeaveRequest::create([
                    'employee_id' => $employee->id,
                    'leave_type_id' => $leaveType->id,
                    'date_from' => $startDate->toDateString(),
                    'date_to' => $endDate->toDateString(),
                    'days_count' => $totalDays,
                    'is_half_day' => $isHalfDay,
                    'half_day_type' => $halfDayType,
                    'reason' => $validated['reason'] ?? 'Sin motivo indicado',
                    'confidential_notes' => $validated['confidential_notes'] ?? null,
                    'attachment_path' => $attachmentPath,
                    'attachment_original_name' => $originalFilename,
                    'status' => $initialStatus,
                    'idempotency_token' => $idempotencyToken,
                ]);

                // 7. Reserva de saldos y allocations (por cada año involucrado)
                $calcService->reserveAllocations($newRequest, $employee, $leaveType, $periodsBreakdown);

                // Si no requería aprobación, aprobar allocations inmediatamente
                if ($initialStatus === 'aprobada') {
                    $calcService->approveAllocations($newRequest);
                }

                // 8. Log de creación
                LeaveApprovalLog::create([
                    'leave_request_id' => $newRequest->id,
                    'approver_id' => auth()->id(),
                    'level' => 'empleado',
                    'action' => 'solicitada',
                    'comment' => 'Solicitud ingresada por el colaborador desde el portal de autogestión',
                    'ip_address' => $request->ip(),
                    'user_agent' => substr((string) $request->userAgent(), 0, 255),
                ]);

                // 9. Auditoría
                HrAuditService::log(
                    'crear_solicitud',
                    'hr_leave_requests',
                    $newRequest->id,
                    null,
                    $newRequest->toArray(),
                    "Creación de solicitud de {$leaveType->name} ({$totalDays} días) por {$employee->full_name}"
                );

                // 10. Notificación a Manager si corresponde
                if ($employee->manager && $employee->manager->user_id && $initialStatus === 'pendiente_manager') {
                    HrNotification::create([
                        'user_id' => $employee->manager->user_id,
                        'title' => 'Nueva Solicitud de Ausencia de Equipo',
                        'message' => "{$employee->full_name} solicitó {$totalDays} días de {$leaveType->name} del {$startDate->format('d/m/Y')} al {$endDate->format('d/m/Y')}.",
                        'type' => 'leave_request_submitted',
                        'data' => ['leave_request_id' => $newRequest->id],
                        'is_read' => false,
                    ]);
                }

                return $newRequest;
            });
        } catch (\InvalidArgumentException $e) {
            return redirect()->back()->withErrors(['dates' => $e->getMessage()])->withInput();
        } catch (\RuntimeException $e) {
            return redirect()->back()->withErrors(['balance' => $e->getMessage()])->withInput();
        }

        return redirect()->route('rrhh.portal.requests')->with('success', 'Solicitud de ausencia creada correctamente.');
    }

    public function cancelRequest(Request $request, LeaveRequest $leaveRequest, HrLeaveCalculationService $calcService)
    {
        $employee = $this->getActiveEmployee();

        if ((int) $leaveRequest->employee_id !== (int) $employee->id) {
            abort(403, 'No tiene autorización para cancelar solicitudes ajenas.');
        }

        if (!in_array($leaveRequest->status, ['pendiente_manager', 'pendiente_rrhh', 'pendiente'])) {
            return redirect()->back()->withErrors([
                'status' => 'Solo es posible cancelar solicitudes que se encuentren en estado pendiente. Las solicitudes aprobadas requieren cancelación administrativa por RR. HH.'
            ]);
        }

        DB::transaction(function () use ($leaveRequest, $request, $employee, $calcService) {
            /** @var LeaveRequest $lockedRequest */
            $lockedRequest = LeaveRequest::where('id', $leaveRequest->id)->lockForUpdate()->first();

            if ($lockedRequest->status === 'cancelada') {
                return; // Idempotencia
            }

            $oldValues = $lockedRequest->toArray();

            $lockedRequest->update([
                'status' => 'cancelada',
                'current_approver_id' => null,
            ]);

            LeaveApprovalLog::create([
                'leave_request_id' => $lockedRequest->id,
                'approver_id' => auth()->id(),
                'level' => 'empleado',
                'action' => 'cancelada_empleado',
                'comment' => $request->comment ?? 'Cancelación voluntaria realizada por el colaborador',
                'ip_address' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 255),
            ]);

            $calcService->releaseAllocations($lockedRequest, false);

            HrAuditService::log(
                'cancelar_empleado',
                'hr_leave_requests',
                $lockedRequest->id,
                $oldValues,
                $lockedRequest->fresh()->toArray(),
                "Cancelación por colaborador de solicitud #{$lockedRequest->id}"
            );
        });

        return redirect()->route('rrhh.portal.requests')->with('success', 'Solicitud cancelada y cupo liberado exitosamente.');
    }

    public function myCalendar(Request $request)
    {
        $employee = $this->getActiveEmployee();

        $monthStr = $request->get('month', Carbon::today()->format('Y-m'));
        $currentMonth = Carbon::createFromFormat('Y-m', $monthStr)->startOfMonth();

        $startCalendar = $currentMonth->copy()->startOfWeek(Carbon::MONDAY);
        $endCalendar = $currentMonth->copy()->endOfMonth()->endOfWeek(Carbon::SUNDAY);

        $leaves = LeaveRequest::with('leaveType')
            ->where('employee_id', $employee->id)
            ->whereIn('status', ['aprobada', 'pendiente_manager', 'pendiente_rrhh', 'pendiente'])
            ->where(function ($q) use ($startCalendar, $endCalendar) {
                $q->where('date_from', '<=', $endCalendar->toDateString())
                  ->where('date_to', '>=', $startCalendar->toDateString());
            })
            ->get();

        $holidays = Holiday::where('is_active', true)
            ->where(function ($q) use ($startCalendar, $endCalendar, $employee) {
                $q->where(function($sub) use ($startCalendar, $endCalendar) {
                    $sub->whereBetween('date', [$startCalendar->toDateString(), $endCalendar->toDateString()])
                        ->orWhere('is_recurring', true);
                });
                if ($employee->branch_id) {
                    $q->where(function($sub) use ($employee) {
                        $sub->where('branch_id', $employee->branch_id)->orWhereNull('branch_id');
                    });
                }
            })
            ->get();

        return view('hr.portal.my_calendar', compact('currentMonth', 'startCalendar', 'endCalendar', 'leaves', 'holidays', 'employee'));
    }

    public function downloadAttachment(LeaveRequest $leaveRequest)
    {
        $employee = $this->getActiveEmployee();

        if ((int) $leaveRequest->employee_id !== (int) $employee->id) {
            abort(403, 'No tiene autorización para descargar comprobantes ajenos.');
        }

        if (!$leaveRequest->attachment_path || !Storage::disk('local')->exists($leaveRequest->attachment_path)) {
            abort(404, 'El archivo adjunto no existe.');
        }

        HrAuditService::log(
            'descargar_comprobante_portal',
            'hr_leave_requests',
            $leaveRequest->id,
            null,
            ['file' => $leaveRequest->attachment_original_name],
            "Descarga de comprobante propio de solicitud #{$leaveRequest->id} por {$employee->full_name}"
        );

        $downloadName = $leaveRequest->attachment_original_name ?: 'comprobante_ausencia.pdf';

        return Storage::disk('local')->download($leaveRequest->attachment_path, $downloadName, [
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-transform',
        ]);
    }
}
