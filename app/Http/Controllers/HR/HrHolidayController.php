<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Http\Requests\HR\HolidayRequest;
use App\Models\HR\Branch;
use App\Models\HR\Holiday;
use App\Services\HR\HrAuditService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class HrHolidayController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'terms.accepted', 'hr.access:admin']);
    }

    public function index(Request $request)
    {
        $year = (int) $request->get('year', Carbon::today()->year);

        $query = Holiday::with('branch')
            ->where(function ($q) use ($year) {
                $q->where('year', $year)
                  ->orWhere('is_recurring', true)
                  ->orWhereYear('date', $year);
            });

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->branch_id);
        }

        $holidays = $query->orderBy('date')->paginate(15)->withQueryString();
        $branches = Branch::where('is_active', true)->orderBy('name')->get();

        return view('hr.holidays.index', compact('holidays', 'branches', 'year'));
    }

    public function create()
    {
        $branches = Branch::where('is_active', true)->orderBy('name')->get();

        return view('hr.holidays.create', compact('branches'));
    }

    public function store(HolidayRequest $request)
    {
        $validated = $request->validated();
        $date = Carbon::parse($validated['date'] ?? $validated['holiday_date']);

        $holiday = Holiday::create([
            'name' => $validated['name'],
            'date' => $date->toDateString(),
            'year' => $date->year,
            'type' => $validated['type'] ?? 'national',
            'is_recurring' => (bool) ($validated['is_recurring'] ?? false),
            'branch_id' => $validated['branch_id'] ?? null,
            'is_active' => (bool) ($validated['is_active'] ?? true),
            'description' => $validated['description'] ?? $validated['notes'] ?? null,
            'created_by' => auth()->id(),
        ]);

        HrAuditService::log(
            'crear',
            'hr_holidays',
            $holiday->id,
            null,
            $holiday->toArray(),
            "Creación de feriado: {$holiday->name} ({$holiday->date->format('d/m/Y')})"
        );

        return redirect()->route('rrhh.holidays.index')->with('success', 'Feriado registrado exitosamente.');
    }

    public function edit(Holiday $holiday)
    {
        $branches = Branch::where('is_active', true)->orderBy('name')->get();

        return view('hr.holidays.edit', compact('holiday', 'branches'));
    }

    public function update(HolidayRequest $request, Holiday $holiday)
    {
        $validated = $request->validated();
        $date = Carbon::parse($validated['date'] ?? $validated['holiday_date']);

        $oldValues = $holiday->toArray();

        $holiday->update([
            'name' => $validated['name'],
            'date' => $date->toDateString(),
            'year' => $date->year,
            'type' => $validated['type'] ?? 'national',
            'is_recurring' => (bool) ($validated['is_recurring'] ?? false),
            'branch_id' => $validated['branch_id'] ?? null,
            'is_active' => (bool) ($validated['is_active'] ?? true),
            'description' => $validated['description'] ?? $validated['notes'] ?? null,
        ]);

        HrAuditService::log(
            'actualizar',
            'hr_holidays',
            $holiday->id,
            $oldValues,
            $holiday->fresh()->toArray(),
            "Actualización de feriado: {$holiday->name}"
        );

        return redirect()->route('rrhh.holidays.index')->with('success', 'Feriado actualizado exitosamente.');
    }

    public function toggleStatus(Holiday $holiday)
    {
        $holiday->update(['is_active' => !$holiday->is_active]);

        HrAuditService::log(
            'cambiar_estado',
            'hr_holidays',
            $holiday->id,
            ['is_active' => !$holiday->is_active],
            ['is_active' => $holiday->is_active],
            "Cambio de estado en feriado #{$holiday->id} ({$holiday->name}) a " . ($holiday->is_active ? 'activo' : 'inactivo')
        );

        return redirect()->back()->with('success', 'Estado del feriado actualizado.');
    }

    public function destroy(Holiday $holiday)
    {
        $oldValues = $holiday->toArray();
        $holiday->delete();

        HrAuditService::log(
            'eliminar',
            'hr_holidays',
            $holiday->id,
            $oldValues,
            null,
            "Eliminación de feriado: {$holiday->name}"
        );

        return redirect()->route('rrhh.holidays.index')->with('success', 'Feriado eliminado correctamente.');
    }
}
