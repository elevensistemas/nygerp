<?php

namespace App\Http\Controllers;

use App\Models\DriverAdvanceRequest;
use App\Models\Transportista;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\Rule;

class AdminAdvanceRequestController extends Controller
{
    public function index(Request $request): View
    {
        $query = DriverAdvanceRequest::query()
            ->with(['transportista', 'resolvedBy']);

        $filters = [
            'transportista_id' => $request->query('transportista_id'),
            'fecha_desde' => $request->query('fecha_desde'),
            'fecha_hasta' => $request->query('fecha_hasta'),
            'estado' => $request->query('estado'),
        ];

        if (!empty($filters['transportista_id'])) {
            $query->where('transportista_id', (int) $filters['transportista_id']);
        }

        if (!empty($filters['fecha_desde'])) {
            $query->whereDate('fecha_pedido', '>=', $filters['fecha_desde']);
        }

        if (!empty($filters['fecha_hasta'])) {
            $query->whereDate('fecha_pedido', '<=', $filters['fecha_hasta']);
        }

        if (!empty($filters['estado'])) {
            $query->where('estado', $filters['estado']);
        }

        $requests = $query->orderByDesc('fecha_pedido')
            ->orderByDesc('id')
            ->paginate(20)
            ->appends($filters);

        $transportistas = Transportista::query()
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('pago-choferes.adelantos.index', [
            'requests' => $requests,
            'transportistas' => $transportistas,
            'filters' => $filters,
        ]);
    }

    public function resolve(Request $request, DriverAdvanceRequest $advanceRequest): RedirectResponse
    {
        if ($advanceRequest->estado === DriverAdvanceRequest::ESTADO_APROBADO && $advanceRequest->recibo_chofer_id !== null) {
            return back()->withErrors('No se puede modificar la resolución ya que el adelanto ya fue liquidado en un recibo.');
        }

        $validated = $request->validate([
            'action' => ['required', Rule::in(['aprobar', 'rechazar', 'contraofertar'])],
            'monto_contraoferta' => [
                'required_if:action,contraofertar',
                'nullable',
                'numeric',
                'min:0.01',
                function ($attribute, $value, $fail) use ($advanceRequest, $request) {
                    if ($request->input('action') === 'contraofertar' && (float) $value >= (float) $advanceRequest->monto_pedido) {
                        $fail('El monto de la contraoferta debe ser menor al monto solicitado ($ ' . number_format((float) $advanceRequest->monto_pedido, 2, ',', '.') . ').');
                    }
                }
            ],
            'observaciones_admin' => ['nullable', 'string', 'max:1000'],
        ], [
            'action.required' => 'La acción de resolución es obligatoria.',
            'monto_contraoferta.required_if' => 'El monto es obligatorio para realizar una contraoferta.',
            'monto_contraoferta.numeric' => 'El monto debe ser un valor numérico.',
            'monto_contraoferta.min' => 'El monto debe ser mayor a cero.',
        ]);

        $action = $validated['action'];
        $notes = $validated['observaciones_admin'] ?? null;
        $adminId = auth()->id();

        if ($action === 'aprobar') {
            $advanceRequest->update([
                'estado' => DriverAdvanceRequest::ESTADO_APROBADO,
                'monto_aprobado' => $advanceRequest->monto_pedido,
                'fecha_resolucion' => now(),
                'resolved_by' => $adminId,
                'observaciones_admin' => $notes,
            ]);
            $message = 'Solicitud de adelanto aprobada.';
        } elseif ($action === 'rechazar') {
            $advanceRequest->update([
                'estado' => DriverAdvanceRequest::ESTADO_RECHAZADO,
                'monto_aprobado' => 0,
                'fecha_resolucion' => now(),
                'resolved_by' => $adminId,
                'observaciones_admin' => $notes,
            ]);
            $message = 'Solicitud de adelanto rechazada.';
        } elseif ($action === 'contraofertar') {
            $advanceRequest->update([
                'estado' => DriverAdvanceRequest::ESTADO_CONTRAOFERTADO,
                'monto_aprobado' => $validated['monto_contraoferta'],
                'fecha_resolucion' => now(),
                'resolved_by' => $adminId,
                'observaciones_admin' => $notes,
            ]);
            $message = 'Contraoferta enviada al chofer.';
        }

        return redirect()
            ->route('pago-choferes.adelantos.index')
            ->with('ok', $message);
    }
}
