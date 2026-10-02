<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\HR\Branch;
use App\Models\HR\Department;
use App\Models\HR\Holiday;
use App\Models\HR\LeaveRequest;
use App\Models\HR\LeaveType;
use App\Models\HR\Position;
use App\Models\HR\Employee;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;

class HrCalendarController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'terms.accepted', 'hr.access:admin']);
    }

    public function index(Request $request)
    {
        if ($request->filled('month') && str_contains($request->month, '-')) {
            $parts = explode('-', $request->month);
            $year = (int) $parts[0];
            $month = (int) $parts[1];
        } else {
            $month = $request->filled('month') ? (int) $request->month : Carbon::today()->month;
            $year = $request->filled('year') ? (int) $request->year : Carbon::today()->year;
        }
        $viewMode = $request->get('view', 'matrix');

        $startOfMonth = Carbon::createFromDate($year, $month, 1)->startOfDay();
        $endOfMonth = $startOfMonth->copy()->endOfMonth()->endOfDay();

        // 1. Consulta de Solicitudes en el rango del mes
        $query = LeaveRequest::with(['employee.department', 'employee.position', 'employee.branch', 'leaveType'])
            ->whereIn('status', ['aprobada', 'pendiente'])
            ->where(function ($q) use ($startOfMonth, $endOfMonth) {
                $q->whereBetween('date_from', [$startOfMonth->toDateString(), $endOfMonth->toDateString()])
                    ->orWhereBetween('date_to', [$startOfMonth->toDateString(), $endOfMonth->toDateString()])
                    ->orWhere(function ($sub) use ($startOfMonth, $endOfMonth) {
                        $sub->where('date_from', '<=', $startOfMonth->toDateString())
                            ->where('date_to', '>=', $endOfMonth->toDateString());
                    });
            });

        if ($request->filled('department_id')) {
            $query->whereHas('employee', function ($q) use ($request) {
                $q->where('department_id', $request->department_id);
            });
        }

        if ($request->filled('position_id')) {
            $query->whereHas('employee', function ($q) use ($request) {
                $q->where('position_id', $request->position_id);
            });
        }

        if ($request->filled('branch_id')) {
            $query->whereHas('employee', function ($q) use ($request) {
                $q->where('branch_id', $request->branch_id);
            });
        }

        if ($request->filled('leave_type_id')) {
            $query->where('leave_type_id', $request->leave_type_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $leaveRequests = $query->orderBy('date_from')->get();

        // 2. Feriados del mes
        $holidays = Holiday::where('is_active', true)
            ->where(function ($q) use ($month, $year) {
                $q->where(function ($sub) use ($month, $year) {
                    $sub->whereYear('date', $year)->whereMonth('date', $month);
                })->orWhere(function ($sub) use ($month) {
                    $sub->where('is_recurring', true)->whereMonth('date', $month);
                });
            })
            ->get();

        // 3. Estructura de la grilla mensual (Mes clásico)
        $firstDayOfGrid = $startOfMonth->copy()->startOfWeek(Carbon::MONDAY);
        $lastDayOfGrid = $endOfMonth->copy()->endOfWeek(Carbon::SUNDAY);
        $gridPeriod = CarbonPeriod::create($firstDayOfGrid, $lastDayOfGrid);

        $eventsByDate = [];
        foreach ($gridPeriod as $d) {
            $dateStr = $d->toDateString();
            $eventsByDate[$dateStr] = [
                'holidays' => [],
                'leaves' => [],
            ];
        }

        // Mapear Feriados
        foreach ($holidays as $h) {
            $hDate = $h->is_recurring ? Carbon::create($year, $h->date->month, $h->date->day)->toDateString() : $h->date->toDateString();
            if (isset($eventsByDate[$hDate])) {
                $eventsByDate[$hDate]['holidays'][] = $h->name;
            }
        }

        // Mapear Licencias por fecha
        $holidaysByDate = [];
        foreach ($holidays as $h) {
            $hDate = $h->is_recurring ? Carbon::create($year, $h->date->month, $h->date->day)->toDateString() : $h->date->toDateString();
            $holidaysByDate[$hDate][] = $h;
        }

        $leavesByDate = [];
        foreach ($leaveRequests as $req) {
            $period = CarbonPeriod::create(
                max($req->date_from, $startOfMonth->toDateString()),
                min($req->date_to, $endOfMonth->toDateString())
            );
            foreach ($period as $d) {
                $leavesByDate[$d->toDateString()][] = $req;
            }
        }

        // 4. Estructura Grilla  por Puesto
        $daysInMonthPeriod = CarbonPeriod::create($startOfMonth, $endOfMonth);

        $positionsQuery = Position::with([
            'department',
            'employees' => function ($q) use ($request) {
                $q->where('status', 'activo')->with(['department', 'position', 'branch', 'agreement', 'leaveBalances.leaveType']);
                if ($request->filled('department_id')) {
                    $q->where('department_id', $request->department_id);
                }
                if ($request->filled('branch_id')) {
                    $q->where('branch_id', $request->branch_id);
                }
            }
        ])->where('is_active', true);

        if ($request->filled('position_id')) {
            $positionsQuery->where('id', $request->position_id);
        }

        if ($request->filled('department_id')) {
            $positionsQuery->where('department_id', $request->department_id);
        }

        $positions = $positionsQuery->orderBy('name')->get();

        // Empleados sin puesto asignado
        $unassignedEmployeesQuery = Employee::with(['department', 'position', 'branch', 'agreement', 'leaveBalances.leaveType'])
            ->whereNull('position_id')
            ->where('status', 'activo');

        if ($request->filled('department_id')) {
            $unassignedEmployeesQuery->where('department_id', $request->department_id);
        }
        if ($request->filled('branch_id')) {
            $unassignedEmployeesQuery->where('branch_id', $request->branch_id);
        }
        $unassignedEmployees = $unassignedEmployeesQuery->get();

        // Detección de solapamientos / superposiciones por puesto
        $positionOverlaps = [];
        $totalOverlapsCount = 0;
        $overlappingPositions = [];

        foreach ($daysInMonthPeriod as $day) {
            $dStr = $day->toDateString();
            $activeLeavesToday = $leavesByDate[$dStr] ?? [];

            $leavesByPosition = [];
            foreach ($activeLeavesToday as $req) {
                $posId = $req->employee->position_id ?? 0;
                $leavesByPosition[$posId][] = $req;
            }

            foreach ($leavesByPosition as $posId => $reqs) {
                if (count($reqs) > 1) {
                    $positionOverlaps[$posId][$dStr] = [
                        'count' => count($reqs),
                        'requests' => $reqs,
                    ];
                    $totalOverlapsCount++;
                    if ($posId > 0) {
                        $posName = $reqs[0]->employee->position->name ?? 'Puesto';
                        $overlappingPositions[$posId] = $posName;
                    } else {
                        $overlappingPositions[0] = 'Sin Puesto Asignado';
                    }
                }
            }
        }

        $selectedMonth = sprintf('%04d-%02d', $year, $month);
        $monthLeaves = $leaveRequests;

        $departments = Department::where('is_active', true)->orderBy('name')->get();
        $allPositions = Position::where('is_active', true)->orderBy('name')->get();
        $branches = Branch::where('is_active', true)->orderBy('name')->get();
        $leaveTypes = LeaveType::where('is_active', true)->orderBy('display_order')->get();

        $prevMonthDate = $startOfMonth->copy()->subMonth();
        $nextMonthDate = $startOfMonth->copy()->addMonth();

        return view('hr.calendar.index', compact(
            'month',
            'year',
            'selectedMonth',
            'startOfMonth',
            'endOfMonth',
            'firstDayOfGrid',
            'lastDayOfGrid',
            'gridPeriod',
            'daysInMonthPeriod',
            'eventsByDate',
            'holidaysByDate',
            'leavesByDate',
            'leaveRequests',
            'monthLeaves',
            'holidays',
            'departments',
            'allPositions',
            'positions',
            'unassignedEmployees',
            'positionOverlaps',
            'totalOverlapsCount',
            'overlappingPositions',
            'branches',
            'leaveTypes',
            'prevMonthDate',
            'nextMonthDate',
            'viewMode'
        ));
    }
}
