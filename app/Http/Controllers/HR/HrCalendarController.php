<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\HR\Branch;
use App\Models\HR\Department;
use App\Models\HR\Holiday;
use App\Models\HR\LeaveRequest;
use App\Models\HR\LeaveType;
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
        $viewMode = $request->get('view', 'month'); // 'month' or 'list'

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

        // 3. Estructura de la grilla mensual
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

        // Mapear Licencias (respetando privacidad)
        foreach ($leaveRequests as $req) {
            $period = CarbonPeriod::create(
                max($req->date_from, $firstDayOfGrid),
                min($req->date_to, $lastDayOfGrid)
            );

            foreach ($period as $d) {
                $dateStr = $d->toDateString();
                if (isset($eventsByDate[$dateStr])) {
                    $eventsByDate[$dateStr]['leaves'][] = [
                        'id' => $req->id,
                        'employee_name' => $req->employee->full_name,
                        'leave_name' => $req->leaveType->name,
                        'color' => $req->leaveType->color,
                        'status' => $req->status,
                        'is_half_day' => $req->is_half_day,
                        'half_day_type' => $req->half_day_type,
                        'department' => $req->employee->department ? $req->employee->department->name : '',
                    ];
                }
            }
        }

        $selectedMonth = sprintf('%04d-%02d', $year, $month);
        $monthLeaves = $leaveRequests;

        $holidaysByDate = [];
        foreach ($holidays as $h) {
            $hDate = $h->is_recurring ? Carbon::create($year, $h->date->month, $h->date->day)->toDateString() : $h->date->toDateString();
            $holidaysByDate[$hDate][] = $h;
        }

        $leavesByDate = [];
        foreach ($leaveRequests as $req) {
            $period = CarbonPeriod::create(
                max($req->date_from, $firstDayOfGrid),
                min($req->date_to, $lastDayOfGrid)
            );
            foreach ($period as $d) {
                $leavesByDate[$d->toDateString()][] = $req;
            }
        }

        $departments = Department::where('is_active', true)->orderBy('name')->get();
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
            'eventsByDate',
            'holidaysByDate',
            'leavesByDate',
            'leaveRequests',
            'monthLeaves',
            'holidays',
            'departments',
            'branches',
            'leaveTypes',
            'prevMonthDate',
            'nextMonthDate',
            'viewMode'
        ));
    }
}
