<?php

namespace App\Services\HR;

use App\Models\HR\Employee;
use App\Models\HR\Holiday;
use App\Models\HR\LeaveBalance;
use App\Models\HR\LeavePolicy;
use App\Models\HR\LeaveRequest;
use App\Models\HR\LeaveRequestAllocation;
use App\Models\HR\LeaveType;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\DB;

class HrLeaveCalculationService
{
    /**
     * Calcula los días computables para un rango de fechas según el tipo de ausencia y feriados.
     */
    /**
     * @param Carbon|string $dateFrom
     * @param Carbon|string $dateTo
     * @param LeaveType|bool|string $leaveTypeOrCountsAsWorking
     */
    public function calculateDays(
        $dateFrom,
        $dateTo,
        $leaveTypeOrCountsAsWorking = false,
        bool $isHalfDay = false,
        ?string $halfDayType = null,
        ?int $branchId = null
    ): array {
        $from = Carbon::parse($dateFrom)->startOfDay();
        $to = Carbon::parse($dateTo)->startOfDay();

        if ($to->lt($from)) {
            throw new \InvalidArgumentException('La fecha final no puede ser anterior a la fecha inicial.');
        }

        $countsAsWorkingDays = false;
        $allowsHalfDay = true;
        if ($leaveTypeOrCountsAsWorking instanceof LeaveType) {
            $countsAsWorkingDays = ($leaveTypeOrCountsAsWorking->calculation_unit === 'habiles' || $leaveTypeOrCountsAsWorking->counts_as_working_days);
            $allowsHalfDay = (bool) $leaveTypeOrCountsAsWorking->allows_half_day;
        } elseif (is_bool($leaveTypeOrCountsAsWorking)) {
            $countsAsWorkingDays = $leaveTypeOrCountsAsWorking;
        } elseif (is_string($leaveTypeOrCountsAsWorking)) {
            $countsAsWorkingDays = ($leaveTypeOrCountsAsWorking === 'habiles' || $leaveTypeOrCountsAsWorking === 'working_days');
        }

        // Caso medio día (debe ser el mismo día)
        if ($isHalfDay && $allowsHalfDay) {
            $isWeekend = $from->isWeekend();
            $isHoliday = $this->isHoliday($from, $branchId);

            return [
                'total_days' => 0.5,
                'computable_days' => 0.5,
                'calendar_days' => 1,
                'working_days' => ($isWeekend || $isHoliday) ? 0 : 1,
                'weekend_days' => $isWeekend ? 1 : 0,
                'holiday_days' => $isHoliday ? 1 : 0,
                'holidays_list' => $isHoliday ? [$this->getHolidayName($from, $branchId)] : [],
                'is_half_day' => true,
                'half_day_type' => $halfDayType ?? 'manana',
            ];
        }

        $period = CarbonPeriod::create($from, $to);
        $calendarDays = 0;
        $workingDays = 0;
        $weekendDays = 0;
        $holidayDays = 0;
        $holidaysList = [];

        foreach ($period as $date) {
            $calendarDays++;
            $isWeekend = $date->isWeekend();
            $isHoliday = $this->isHoliday($date, $branchId);

            if ($isWeekend) {
                $weekendDays++;
            }

            if ($isHoliday) {
                $holidayDays++;
                $holidaysList[] = $this->getHolidayName($date, $branchId) . ' (' . $date->format('d/m') . ')';
            }

            if (!$isWeekend && !$isHoliday) {
                $workingDays++;
            }
        }

        // Según la unidad de cálculo:
        $totalComputable = $countsAsWorkingDays ? (float) $workingDays : (float) $calendarDays;

        return [
            'total_days' => $totalComputable,
            'computable_days' => $totalComputable,
            'calendar_days' => $calendarDays,
            'working_days' => $workingDays,
            'weekend_days' => $weekendDays,
            'holiday_days' => $holidayDays,
            'holidays_list' => array_unique($holidaysList),
            'is_half_day' => false,
            'half_day_type' => null,
        ];
    }

    /**
     * Divide y calcula días computables agrupados por año / período calendario.
     * Útil para solicitudes que cruzan el 31 de diciembre.
     */
    /**
     * @param Carbon|string $dateFrom
     * @param Carbon|string $dateTo
     * @param LeaveType|bool|string $leaveTypeOrCountsAsWorking
     */
    public function calculateDaysByPeriod(
        $dateFrom,
        $dateTo,
        $leaveTypeOrCountsAsWorking = false,
        bool $isHalfDay = false,
        ?string $halfDayType = null,
        ?int $branchId = null
    ): array {
        $from = Carbon::parse($dateFrom)->startOfDay();
        $to = Carbon::parse($dateTo)->startOfDay();

        if ($from->year === $to->year) {
            $calc = $this->calculateDays($from, $to, $leaveTypeOrCountsAsWorking, $isHalfDay, $halfDayType, $branchId);
            return [
                $from->year => [
                    'year' => $from->year,
                    'start_date' => $from->toDateString(),
                    'end_date' => $to->toDateString(),
                    'days' => $calc['computable_days'],
                    'details' => $calc,
                ]
            ];
        }

        $periods = [];
        $currentYear = $from->year;

        while ($currentYear <= $to->year) {
            $periodStart = ($currentYear === $from->year) ? $from->copy() : Carbon::create($currentYear, 1, 1)->startOfDay();
            $periodEnd = ($currentYear === $to->year) ? $to->copy() : Carbon::create($currentYear, 12, 31)->startOfDay();

            $calc = $this->calculateDays($periodStart, $periodEnd, $leaveTypeOrCountsAsWorking, false, null, $branchId);

            if ($calc['computable_days'] > 0 || $currentYear === $from->year || $currentYear === $to->year) {
                $periods[$currentYear] = [
                    'year' => $currentYear,
                    'start_date' => $periodStart->toDateString(),
                    'end_date' => $periodEnd->toDateString(),
                    'days' => $calc['computable_days'],
                    'details' => $calc,
                ];
            }

            $currentYear++;
        }

        return $periods;
    }

    /**
     * Verifica si una fecha determinada coincide con un feriado activo.
     */
    public function isHoliday(Carbon $date, ?int $branchId = null): bool
    {
        return Holiday::where('is_active', true)
            ->where(function ($q) use ($branchId) {
                if ($branchId) {
                    $q->where('branch_id', $branchId)
                      ->orWhereNull('branch_id');
                } else {
                    $q->whereNull('branch_id');
                }
            })
            ->where(function ($q) use ($date) {
                // Fecha exacta (para todos los feriados del año o móviles)
                $q->where('date', $date->toDateString())
                  // Feriados recurrentes de fecha fija
                  ->orWhere(function ($sub) use ($date) {
                      $sub->where('is_recurring', true)
                          ->whereMonth('date', $date->month)
                          ->whereDay('date', $date->day);
                  });
            })
            ->exists();
    }

    /**
     * Retorna el nombre del feriado si existe.
     */
    public function getHolidayName(Carbon $date, ?int $branchId = null): ?string
    {
        $holiday = Holiday::where('is_active', true)
            ->where(function ($q) use ($branchId) {
                if ($branchId) {
                    $q->where('branch_id', $branchId)
                      ->orWhereNull('branch_id');
                } else {
                    $q->whereNull('branch_id');
                }
            })
            ->where(function ($q) use ($date) {
                $q->where('date', $date->toDateString())
                  ->orWhere(function ($sub) use ($date) {
                      $sub->where('is_recurring', true)
                          ->whereMonth('date', $date->month)
                          ->whereDay('date', $date->day);
                  });
            })
            ->first();

        return $holiday ? $holiday->name : null;
    }

    /**
     * Determina la política activa aplicable según prioridad:
     * 1. Convenio específico + rango de antigüedad.
     * 2. General (sin convenio) + rango de antigüedad.
     * 3. null (usa días base de LeaveType).
     */
    public function determinePolicy(Employee $employee, LeaveType $leaveType): ?LeavePolicy
    {
        $seniority = $employee->seniority_years;

        // Prioridad 1: Convenio específico
        if ($employee->agreement_id) {
            $agreementPolicy = LeavePolicy::where('leave_type_id', $leaveType->id)
                ->where('agreement_id', $employee->agreement_id)
                ->where('is_active', true)
                ->where('seniority_years_from', '<=', $seniority)
                ->where(function ($q) use ($seniority) {
                    $q->whereNull('seniority_years_to')
                      ->orWhere('seniority_years_to', '>=', $seniority);
                })
                ->orderBy('seniority_years_from', 'desc')
                ->first();

            if ($agreementPolicy) {
                return $agreementPolicy;
            }
        }

        // Prioridad 2: Política general (sin convenio)
        return LeavePolicy::where('leave_type_id', $leaveType->id)
            ->whereNull('agreement_id')
            ->where('is_active', true)
            ->where('seniority_years_from', '<=', $seniority)
            ->where(function ($q) use ($seniority) {
                $q->whereNull('seniority_years_to')
                  ->orWhere('seniority_years_to', '>=', $seniority);
            })
            ->orderBy('seniority_years_from', 'desc')
            ->first();
    }

    /**
     * Obtiene o inicializa el saldo con snapshot de política.
     */
    public function getOrCreateBalance(Employee $employee, LeaveType $leaveType, int $year): LeaveBalance
    {
        $balance = LeaveBalance::where('employee_id', $employee->id)
            ->where('leave_type_id', $leaveType->id)
            ->where('period_year', $year)
            ->first();

        if (!$balance) {
            $policy = $this->determinePolicy($employee, $leaveType);
            $assignedDays = $policy ? (float) $policy->days_granted : (float) ($leaveType->days_allowed_per_year ?? 0);

            $policySnapshot = $policy ? [
                'policy_id' => $policy->id,
                'name' => $policy->name,
                'seniority_years_from' => $policy->seniority_years_from,
                'seniority_years_to' => $policy->seniority_years_to,
                'days_granted' => $policy->days_granted,
                'allow_transfer' => $policy->allow_transfer,
                'created_at_snapshot' => now()->toIso8601String(),
            ] : null;

            $balance = LeaveBalance::create([
                'employee_id' => $employee->id,
                'leave_type_id' => $leaveType->id,
                'leave_policy_id' => optional($policy)->id,
                'policy_snapshot' => $policySnapshot,
                'period_year' => $year,
                'assigned_days' => $assignedDays,
                'transferred_days' => 0,
                'adjustment_days' => 0,
                'used_days' => 0,
                'pending_days' => 0,
                'available_days' => $assignedDays,
            ]);
        }

        return $balance;
    }

    /**
     * Sincroniza y recalcula con bloqueo transaccional el saldo de un empleado para un año.
     * Fórmula:
     * Total otorgado = assigned_days + transferred_days + adjustment_days
     * Saldo disponible = Total otorgado - (used_days + pending_days)
     */
    public function syncBalance(Employee $employee, LeaveType $leaveType, int $year): LeaveBalance
    {
        $balance = $this->getOrCreateBalance($employee, $leaveType, $year);

        // Suma de días aprobados (used) desde allocations o solicitudes
        $allocationsUsed = LeaveRequestAllocation::where('leave_balance_id', $balance->id)
            ->where('status', 'used')
            ->sum('days_allocated');

        $allocationsPending = LeaveRequestAllocation::where('leave_balance_id', $balance->id)
            ->where('status', 'pending')
            ->sum('days_allocated');

        // Fallback a solicitudes directas si no hubiera allocations registradas
        if ($allocationsUsed == 0 && $allocationsPending == 0) {
            $usedDays = (float) LeaveRequest::where('employee_id', $employee->id)
                ->where('leave_type_id', $leaveType->id)
                ->whereYear('date_from', $year)
                ->where('status', 'aprobada')
                ->sum('days_count');

            $pendingDays = (float) LeaveRequest::where('employee_id', $employee->id)
                ->where('leave_type_id', $leaveType->id)
                ->whereYear('date_from', $year)
                ->whereIn('status', ['pendiente_manager', 'pendiente_rrhh', 'pendiente'])
                ->sum('days_count');
        } else {
            $usedDays = (float) $allocationsUsed;
            $pendingDays = (float) $allocationsPending;
        }

        $totalGranted = (float) $balance->assigned_days + (float) $balance->transferred_days + (float) $balance->adjustment_days;
        $availableDays = $totalGranted - ($usedDays + $pendingDays);

        $balance->update([
            'used_days' => $usedDays,
            'pending_days' => $pendingDays,
            'available_days' => $availableDays,
        ]);

        return $balance->fresh();
    }

    /**
     * Reconcilia integralmente un saldo con la base de datos de solicitudes y ajustes.
     * Detecta discrepancias en pending_days, used_days, adjustment_days y available_days,
     * corrigiéndolas e insertando un registro en el Registro de Auditoría si hubieron diferencias.
     */
    public function reconcileBalance(LeaveBalance $balance): LeaveBalance
    {
        return DB::transaction(function () use ($balance) {
            /** @var LeaveBalance $locked */
            $locked = LeaveBalance::where('id', $balance->id)->lockForUpdate()->first();
            $oldValues = $locked->toArray();

            // 1. Recalcular días aprobados (used) y pendientes (pending)
            $allocationsUsed = LeaveRequestAllocation::where('leave_balance_id', $locked->id)
                ->where('status', 'used')
                ->sum('days_allocated');

            $allocationsPending = LeaveRequestAllocation::where('leave_balance_id', $locked->id)
                ->where('status', 'pending')
                ->sum('days_allocated');

            if ($allocationsUsed == 0 && $allocationsPending == 0) {
                $expectedUsed = (float) LeaveRequest::where('employee_id', $locked->employee_id)
                    ->where('leave_type_id', $locked->leave_type_id)
                    ->whereYear('date_from', $locked->period_year)
                    ->where('status', 'aprobada')
                    ->sum('days_count');

                $expectedPending = (float) LeaveRequest::where('employee_id', $locked->employee_id)
                    ->where('leave_type_id', $locked->leave_type_id)
                    ->whereYear('date_from', $locked->period_year)
                    ->whereIn('status', ['pendiente_manager', 'pendiente_rrhh', 'pendiente'])
                    ->sum('days_count');
            } else {
                $expectedUsed = (float) $allocationsUsed;
                $expectedPending = (float) $allocationsPending;
            }

            // 2. Recalcular suma neta de ajustes manuales desde hr_leave_balance_adjustments
            $expectedAdjustments = (float) \App\Models\HR\LeaveBalanceAdjustment::where('employee_id', $locked->employee_id)
                ->where('leave_type_id', $locked->leave_type_id)
                ->where('period_year', $locked->period_year)
                ->sum('difference');

            // 3. assigned_days y policy_snapshot NUNCA se alteran
            $assignedDays = (float) $locked->assigned_days;
            $transferredDays = (float) $locked->transferred_days;
            $expectedTotalGranted = $assignedDays + $transferredDays + $expectedAdjustments;
            $expectedAvailable = $expectedTotalGranted - ($expectedUsed + $expectedPending);

            $hadDifference = (
                round((float)$locked->used_days, 1) !== round((float)$expectedUsed, 1) ||
                round((float)$locked->pending_days, 1) !== round((float)$expectedPending, 1) ||
                round((float)$locked->adjustment_days, 1) !== round((float)$expectedAdjustments, 1) ||
                round((float)$locked->available_days, 1) !== round((float)$expectedAvailable, 1)
            );

            $locked->update([
                'used_days' => $expectedUsed,
                'pending_days' => $expectedPending,
                'adjustment_days' => $expectedAdjustments,
                'available_days' => $expectedAvailable,
            ]);

            if ($hadDifference) {
                HrAuditService::log(
                    'reconciliacion_saldo',
                    'hr_leave_balances',
                    $locked->id,
                    $oldValues,
                    $locked->fresh()->toArray(),
                    "Reconciliación de saldo: corrección de discrepancias en período {$locked->period_year} para {$locked->employee->full_name} ({$locked->leaveType->name})."
                );
            }

            return $locked->fresh();
        });
    }

    /**
     * Reserva allocations en los saldos involucrados (un solo año o multianual).
     */
    public function reserveAllocations(LeaveRequest $request, Employee $employee, LeaveType $leaveType, array $periodBreakdown): void
    {
        foreach ($periodBreakdown as $year => $periodData) {
            $daysInPeriod = (float) $periodData['days'];
            if ($daysInPeriod <= 0) {
                continue;
            }

            $balance = $this->getOrCreateBalance($employee, $leaveType, $year);
            $lockedBalance = LeaveBalance::where('id', $balance->id)->lockForUpdate()->first();

            // Validar si período está cerrado administrativamente
            if ($lockedBalance->is_closed) {
                throw new \RuntimeException("El período {$year} se encuentra cerrado administrativamente y no admite nuevas solicitudes.");
            }

            // Validar cupo
            if (!$leaveType->allows_negative_balance && $lockedBalance->available_days < $daysInPeriod) {
                throw new \RuntimeException("Saldo insuficiente en el período {$year}. Disponible: {$lockedBalance->available_days}, Solicitado: {$daysInPeriod}.");
            }

            // Crear allocation
            LeaveRequestAllocation::create([
                'leave_request_id' => $request->id,
                'leave_balance_id' => $lockedBalance->id,
                'period_year' => $year,
                'days_allocated' => $daysInPeriod,
                'status' => 'pending',
            ]);

            // Actualizar pending_days y available_days
            $lockedBalance->pending_days = (float) $lockedBalance->pending_days + $daysInPeriod;
            $lockedBalance->available_days = $lockedBalance->total_granted - ((float) $lockedBalance->used_days + (float) $lockedBalance->pending_days);
            $lockedBalance->save();
        }
    }

    /**
     * Aplica la aprobación definitiva sobre todas las allocations de la solicitud.
     */
    public function approveAllocations(LeaveRequest $request): void
    {
        $allocations = LeaveRequestAllocation::where('leave_request_id', $request->id)
            ->where('status', 'pending')
            ->get();

        if ($allocations->isEmpty()) {
            // Fallback para solicitudes sin tabla de allocation
            $balance = $this->getOrCreateBalance($request->employee, $request->leaveType, $request->date_from->year);
            $lockedBalance = LeaveBalance::where('id', $balance->id)->lockForUpdate()->first();

            if ($lockedBalance->is_closed) {
                throw new \RuntimeException("No es posible aprobar la solicitud: el período {$lockedBalance->period_year} se encuentra cerrado administrativamente.");
            }

            $days = (float) $request->days_count;
            $lockedBalance->pending_days = max(0, (float) $lockedBalance->pending_days - $days);
            $lockedBalance->used_days = (float) $lockedBalance->used_days + $days;
            $lockedBalance->available_days = $lockedBalance->total_granted - ((float) $lockedBalance->used_days + (float) $lockedBalance->pending_days);
            $lockedBalance->save();
            return;
        }

        foreach ($allocations as $alloc) {
            $lockedBalance = LeaveBalance::where('id', $alloc->leave_balance_id)->lockForUpdate()->first();

            if ($lockedBalance->is_closed) {
                throw new \RuntimeException("No es posible aprobar la solicitud: el período {$lockedBalance->period_year} se encuentra cerrado administrativamente.");
            }

            $alloc->update(['status' => 'used']);

            $days = (float) $alloc->days_allocated;
            $lockedBalance->pending_days = max(0, (float) $lockedBalance->pending_days - $days);
            $lockedBalance->used_days = (float) $lockedBalance->used_days + $days;
            $lockedBalance->available_days = $lockedBalance->total_granted - ((float) $lockedBalance->used_days + (float) $lockedBalance->pending_days);
            $lockedBalance->save();
        }
    }

    /**
     * Libera allocations tras rechazo o cancelación.
     */
    public function releaseAllocations(LeaveRequest $request, bool $wasApproved = false): void
    {
        $allocations = LeaveRequestAllocation::where('leave_request_id', $request->id)
            ->whereIn('status', ['pending', 'used'])
            ->get();

        if ($allocations->isEmpty()) {
            $balance = $this->getOrCreateBalance($request->employee, $request->leaveType, $request->date_from->year);
            $lockedBalance = LeaveBalance::where('id', $balance->id)->lockForUpdate()->first();

            $days = (float) $request->days_count;
            if ($wasApproved) {
                $lockedBalance->used_days = max(0, (float) $lockedBalance->used_days - $days);
            } else {
                $lockedBalance->pending_days = max(0, (float) $lockedBalance->pending_days - $days);
            }
            $lockedBalance->available_days = $lockedBalance->total_granted - ((float) $lockedBalance->used_days + (float) $lockedBalance->pending_days);
            $lockedBalance->save();
            return;
        }

        foreach ($allocations as $alloc) {
            $lockedBalance = LeaveBalance::where('id', $alloc->leave_balance_id)->lockForUpdate()->first();

            $days = (float) $alloc->days_allocated;
            if ($alloc->status === 'used') {
                $lockedBalance->used_days = max(0, (float) $lockedBalance->used_days - $days);
            } else {
                $lockedBalance->pending_days = max(0, (float) $lockedBalance->pending_days - $days);
            }

            $alloc->update(['status' => 'released']);
            $lockedBalance->available_days = $lockedBalance->total_granted - ((float) $lockedBalance->used_days + (float) $lockedBalance->pending_days);
            $lockedBalance->save();
        }
    }

    /**
     * Verifica superposiciones con solicitudes existentes respetando franjas de medio día.
     */
    /**
     * @param Employee|int $employee
     * @param Carbon|string $dateFrom
     * @param Carbon|string $dateTo
     */
    public function findOverlap(
        $employee,
        $dateFrom,
        $dateTo,
        bool $isHalfDay = false,
        ?string $halfDayType = null,
        ?int $ignoreRequestId = null
    ): ?LeaveRequest {
        $employeeId = ($employee instanceof Employee) ? $employee->id : (int) $employee;
        $from = Carbon::parse($dateFrom)->toDateString();
        $to = Carbon::parse($dateTo)->toDateString();

        $query = LeaveRequest::where('employee_id', $employeeId)
            ->whereIn('status', ['pendiente_manager', 'pendiente_rrhh', 'pendiente', 'aprobada'])
            ->where(function ($q) use ($from, $to) {
                $q->where('date_from', '<=', $to)
                  ->where('date_to', '>=', $from);
            });

        if ($ignoreRequestId) {
            $query->where('id', '!=', $ignoreRequestId);
        }

        $conflicts = $query->get();

        foreach ($conflicts as $conflict) {
            // Caso especial: Ambos son medio día en la misma fecha única
            if ($isHalfDay && $conflict->is_half_day && $from === $to && $conflict->date_from->toDateString() === $from) {
                $newSlot = $halfDayType ?? 'manana';
                $existingSlot = $conflict->half_day_type ?? 'manana';

                // Si una es mañana y otra tarde, PUEDEN coexistir
                if (($newSlot === 'manana' && $existingSlot === 'tarde') || ($newSlot === 'tarde' && $existingSlot === 'manana')) {
                    continue; // No hay colisión
                }
            }

            // En cualquier otro caso de solapamiento de fechas, hay colisión
            return $conflict;
        }

        return null;
    }
}
