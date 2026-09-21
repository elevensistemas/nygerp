<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\HR\Bulletin;
use App\Models\HR\Department;
use App\Models\HR\DocumentAssignment;
use App\Models\HR\Employee;
use App\Models\HR\Event;
use App\Models\HR\HrAuditLog;
use App\Models\HR\LeaveRequest;
use App\Models\HR\Onboarding;
use App\Models\HR\Position;
use Carbon\Carbon;
use Illuminate\Http\Request;

class HrDashboardController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'terms.accepted', 'hr.access:admin']);
    }

    public function index()
    {
        $today = Carbon::today();

        // Métricas en tiempo real de tablas hr_
        $stats = [
            'total_active_employees' => Employee::where('status', 'activo')->count(),
            'total_employees' => Employee::count(),
            'absent_today' => LeaveRequest::where('status', 'aprobada')
                ->where('date_from', '<=', $today)
                ->where('date_to', '>=', $today)
                ->count(),
            'pending_leaves' => LeaveRequest::where('status', 'pendiente')->count(),
            'pending_documents' => DocumentAssignment::whereIn('status', ['pendiente_lectura', 'pendiente_firma'])->count(),
            'signed_documents' => DocumentAssignment::whereIn('status', ['firmado_conforme', 'firmado_disconforme'])->count(),
            'active_onboardings' => Onboarding::whereNotIn('status', ['completado', 'cancelado'])->count(),
            'departments_count' => Department::where('is_active', true)->count(),
            'positions_count' => Position::where('is_active', true)->count(),
        ];

        // Cumpleaños del mes actual
        $birthdaysThisMonth = Employee::where('status', 'activo')
            ->whereNotNull('birth_date')
            ->whereRaw('MONTH(birth_date) = ?', [$today->month])
            ->orderByRaw('DAY(birth_date) ASC')
            ->take(6)
            ->get();

        // Próximos eventos (próximos 30 días)
        $upcomingEvents = Event::where('start_date', '>=', $today)
            ->where('start_date', '<=', $today->copy()->addDays(30))
            ->orderBy('start_date', 'asc')
            ->take(5)
            ->get();

        // Comunicados / Cartelera recientes
        $recentBulletins = Bulletin::where('status', 'publicado')
            ->latest('published_at')
            ->take(4)
            ->get();

        // Solicitudes de ausencias pendientes recientes
        $recentPendingLeaves = LeaveRequest::with(['employee.department', 'leaveType'])
            ->where('status', 'pendiente')
            ->latest()
            ->take(5)
            ->get();

        // Onboardings en curso
        $recentOnboardings = Onboarding::with(['department', 'position'])
            ->whereNotIn('status', ['completado', 'cancelado'])
            ->latest()
            ->take(4)
            ->get();

        // Registros recientes de auditoría
        $recentAudits = HrAuditLog::with('user')
            ->latest()
            ->take(6)
            ->get();

        return view('hr.dashboard', compact(
            'stats',
            'birthdaysThisMonth',
            'upcomingEvents',
            'recentBulletins',
            'recentPendingLeaves',
            'recentOnboardings',
            'recentAudits'
        ));
    }
}
