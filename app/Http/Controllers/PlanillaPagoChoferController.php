<?php

namespace App\Http\Controllers;

use App\Http\Requests\PlanillaPagoChoferStoreRequest;
use App\Models\DriverPaymentType;
use App\Models\PlanillaPagoChofer;
use App\Models\PlanillaPagoChoferRecibo;
use App\Models\ReciboChofer;
use App\Models\Transportista;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PlanillaPagoChoferController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', PlanillaPagoChofer::class);

        $query = PlanillaPagoChofer::query()->withCount('reciboLinks');

        if ($request->filled('desde')) {
            $query->whereDate('fecha', '>=', $request->input('desde'));
        }

        if ($request->filled('hasta')) {
            $query->whereDate('fecha', '<=', $request->input('hasta'));
        }

        if ($request->filled('estado')) {
            $query->where('estado', $request->input('estado'));
        }

        if ($request->filled('transportista_id')) {
            $query->whereHas('reciboLinks.recibo', function ($q) use ($request) {
                $q->where('transportista_id', $request->input('transportista_id'));
            });
        }

        $planillas = $query->orderByDesc('fecha')->orderByDesc('id')->paginate(20)->withQueryString();
        $transportistas = Transportista::orderBy('name')->get();

        return view('pago-choferes.planillas.index', [
            'planillas' => $planillas,
            'transportistas' => $transportistas,
            'estados' => [
                PlanillaPagoChofer::ESTADO_BORRADOR => 'Borrador',
                PlanillaPagoChofer::ESTADO_CONFIRMADA => 'Confirmada',
                PlanillaPagoChofer::ESTADO_PAGADA_PARCIAL => 'Pagada Parcial',
                PlanillaPagoChofer::ESTADO_CERRADA => 'Cerrada',
            ],
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', PlanillaPagoChofer::class);

        $filters = [
            'transportista_id' => $request->query('transportista_id'),
            'search' => $request->query('search'),
            'tipo_periodo' => $request->query('tipo_periodo'),
            'estado' => $request->query('estado'),
            'zona' => $request->query('zona'),
        ];

        $recibosQuery = ReciboChofer::query()
            ->with(['transportista', 'paymentFleet', 'items'])
            ->whereIn('estado', [ReciboChofer::ESTADO_CARGADO, ReciboChofer::ESTADO_PENDIENTE_PAGO])
            ->doesntHave('planillaLinks')
            ->orderBy('transportista_id')
            ->orderBy('periodo_desde');

        if (! empty($filters['transportista_id'])) {
            $recibosQuery->where('transportista_id', (int) $filters['transportista_id']);
        }

        if (! empty($filters['tipo_periodo'])) {
            $recibosQuery->where('tipo_periodo', $filters['tipo_periodo']);
        }

        if (! empty($filters['estado'])) {
            $recibosQuery->where('estado', $filters['estado']);
        }

        if (! empty($filters['zona'])) {
            $zona = trim((string) $filters['zona']);
            $recibosQuery->whereHas('items', function ($sub) use ($zona) {
                $sub->where('zona', 'like', '%' . $zona . '%')
                    ->orWhere('meta', 'like', '%' . $zona . '%');
            });
        }

        if (! empty($filters['search'])) {
            $search = trim((string) $filters['search']);
            $recibosQuery->where(function ($query) use ($search) {
                $query->where('id', $search)
                    ->orWhereHas('transportista', function ($sub) use ($search) {
                        $sub->where('name', 'like', '%' . $search . '%');
                    })
                    ->orWhereHas('items', function ($sub) use ($search) {
                        $sub->where('concepto', 'like', '%' . $search . '%')
                            ->orWhere('meta', 'like', '%' . $search . '%');
                    });
            });
        }

        $recibos = $recibosQuery->paginate(30)->withQueryString();

        return view('pago-choferes.planillas.create', [
            'recibos' => $recibos,
            'filters' => $filters,
            'transportistas' => Transportista::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(PlanillaPagoChoferStoreRequest $request): RedirectResponse
    {
        $this->authorize('create', PlanillaPagoChofer::class);

        $data = $request->validated();
        $user = $request->user();

        $planilla = null;
        $skippedReciboIds = [];

        DB::transaction(function () use ($data, $user, &$planilla, &$skippedReciboIds) {
            $selectedReciboIds = collect($data['recibo_ids'])
                ->map(static fn ($id) => (int) $id)
                ->unique()
                ->values();

            $recibos = ReciboChofer::whereIn('id', $selectedReciboIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $alreadyAssignedReciboIds = PlanillaPagoChoferRecibo::query()
                ->whereIn('recibo_chofer_id', $selectedReciboIds)
                ->pluck('recibo_chofer_id')
                ->map(static fn ($id) => (int) $id);

            $assignableReciboIds = $selectedReciboIds
                ->diff($alreadyAssignedReciboIds)
                ->values();

            $skippedReciboIds = $alreadyAssignedReciboIds->all();

            if ($assignableReciboIds->isEmpty()) {
                throw ValidationException::withMessages([
                    'recibo_ids' => 'Los recibos seleccionados ya están asignados a otra planilla.',
                ]);
            }

            $planilla = PlanillaPagoChofer::create([
                'numero' => $this->nextNumero(),
                'fecha' => Carbon::parse($data['fecha'])->toDateString(),
                'estado' => PlanillaPagoChofer::ESTADO_CONFIRMADA,
                'observaciones' => $data['observaciones'] ?? null,
                'created_by' => optional($user)->id,
                'updated_by' => optional($user)->id,
            ]);

            foreach ($assignableReciboIds as $reciboId) {
                $recibo = $recibos->get($reciboId);
                if (! $recibo) {
                    continue;
                }

                PlanillaPagoChoferRecibo::create([
                    'planilla_pago_chofer_id' => $planilla->id,
                    'recibo_chofer_id' => $recibo->id,
                    'monto_en_planilla' => $recibo->importe_total,
                    'estado_pago' => PlanillaPagoChoferRecibo::ESTADO_SIN_RESULTADO,
                ]);

                $recibo->estado = ReciboChofer::ESTADO_EN_PLANILLA;
                $recibo->updated_by = optional($user)->id;
                $recibo->save();
            }

            $planilla->recalculateTotal();
        });

        $redirect = redirect()
            ->route('pago-choferes.planillas.show', $planilla)
            ->with('ok', 'Planilla creada.');

        if (! empty($skippedReciboIds)) {
            $redirect->with(
                'warning',
                'Algunos recibos no se agregaron porque ya estaban asignados a otra planilla: #' . implode(', #', $skippedReciboIds)
            );
        }

        return $redirect;
    }

    public function show(PlanillaPagoChofer $planilla): View
    {
        $this->authorize('view', $planilla);

        $perPage = (int) request()->query('per_page', 25);
        if (! in_array($perPage, [10, 25, 50, 100], true)) {
            $perPage = 25;
        }

        $reciboLinks = $planilla->reciboLinks()
            ->with(['recibo.transportista.defaultPaymentMethod.bank', 'recibo.paymentFleet', 'paymentType'])
            ->orderBy('id')
            ->paginate($perPage)
            ->appends(request()->query());

        return view('pago-choferes.planillas.show', [
            'planilla' => $planilla,
            'reciboLinks' => $reciboLinks,
            'perPage' => $perPage,
            'perPageOptions' => [10, 25, 50, 100],
            'paymentTypes' => DriverPaymentType::query()->orderByDesc('type')->orderBy('description')->get(),
        ]);
    }

    public function destroy(Request $request, PlanillaPagoChofer $planilla): RedirectResponse
    {
        $this->authorize('delete', $planilla);

        DB::transaction(function () use ($planilla, $request) {
            $links = $planilla->reciboLinks()->with('recibo')->lockForUpdate()->get();

            if (! $planilla->isDeletableUnprocessed()) {
                abort(422, 'La planilla ya tiene movimientos procesados.');
            }

            foreach ($links as $link) {
                $recibo = $link->recibo;
                if (! $recibo) {
                    continue;
                }

                // Al eliminar la planilla se liberan todos los recibos asociados
                // para permitir reasignarlos o eliminarlos.
                $recibo->estado = ReciboChofer::ESTADO_CARGADO;
                $recibo->updated_by = optional($request->user())->id;
                $recibo->save();
            }

            $planilla->delete();
        });

        return redirect()->route('pago-choferes.planillas.index')->with('ok', 'Planilla eliminada.');
    }

    public function confirm(Request $request, PlanillaPagoChofer $planilla): RedirectResponse
    {
        $this->authorize('confirm', $planilla);

        DB::transaction(function () use ($planilla, $request) {
            foreach ($planilla->reciboLinks()->with('recibo')->get() as $link) {
                $recibo = $link->recibo;
                if (! $recibo) {
                    continue;
                }

                if ($recibo->estado !== ReciboChofer::ESTADO_EN_PLANILLA) {
                    $recibo->estado = ReciboChofer::ESTADO_EN_PLANILLA;
                }
                $recibo->updated_by = optional($request->user())->id;
                $recibo->save();
            }

            $planilla->estado = PlanillaPagoChofer::ESTADO_CONFIRMADA;
            $planilla->updated_by = optional($request->user())->id;
            $planilla->save();
        });

        return redirect()->route('pago-choferes.planillas.show', $planilla)->with('ok', 'Planilla confirmada.');
    }

    public function close(Request $request, PlanillaPagoChofer $planilla): RedirectResponse
    {
        $this->authorize('conciliate', $planilla);

        $planilla->estado = PlanillaPagoChofer::ESTADO_CERRADA;
        $planilla->updated_by = optional($request->user())->id;
        $planilla->save();

        return redirect()->route('pago-choferes.planillas.show', $planilla)->with('ok', 'Planilla cerrada.');
    }

    public function print(PlanillaPagoChofer $planilla): View
    {
        $this->authorize('view', $planilla);
        $planilla->load(['reciboLinks.recibo.transportista.defaultPaymentMethod.bank', 'reciboLinks.recibo.paymentFleet']);

        return view('pago-choferes.planillas.print', [
            'planilla' => $planilla,
        ]);
    }

    private function nextNumero(): string
    {
        $prefix = 'PC-' . now()->format('Ym') . '-';
        $last = PlanillaPagoChofer::where('numero', 'like', $prefix . '%')->orderByDesc('id')->first();

        if (! $last) {
            return $prefix . '0001';
        }

        $sequence = (int) substr($last->numero, -4);

        return $prefix . str_pad((string) ($sequence + 1), 4, '0', STR_PAD_LEFT);
    }
}
