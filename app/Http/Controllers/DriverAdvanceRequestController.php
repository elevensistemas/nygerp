<?php

namespace App\Http\Controllers;

use App\Models\DriverAdvanceRequest;
use App\Models\Transportista;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;

class DriverAdvanceRequestController extends Controller
{
    private function getTransportista(Request $request): ?Transportista
    {
        $user = $request->user();
        if ($user->isTransportista()) {
            return $user->transportistaProfile ?? Transportista::where('email', $user->email)->first();
        }
        return null;
    }

    public function index(Request $request): View
    {
        $transportista = $this->getTransportista($request);
        if (!$transportista) {
            abort(403, 'No tienes perfil de transportista asociado o tu email no fue encontrado.');
        }

        $requests = DriverAdvanceRequest::query()
            ->where('transportista_id', $transportista->id)
            ->orderByDesc('fecha_pedido')
            ->orderByDesc('id')
            ->paginate(15);

        $isBlocked = $transportista->isAdvanceBlocked();
        $hasRequestedThisMonth = $transportista->hasAdvanceThisMonth();

        return view('portal-choferes.adelantos.index', [
            'requests' => $requests,
            'isBlocked' => $isBlocked,
            'hasRequestedThisMonth' => $hasRequestedThisMonth,
            'transportista' => $transportista,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $transportista = $this->getTransportista($request);
        if (!$transportista) {
            abort(403, 'No tienes permiso para solicitar adelantos.');
        }

        if ($transportista->isAdvanceBlocked()) {
            return back()->withErrors('Tu posibilidad de solicitar adelantos está deshabilitada temporalmente.');
        }

        if ($transportista->hasAdvanceThisMonth()) {
            return back()->withErrors('Solo está permitido realizar una solicitud de adelanto por mes.');
        }

        $validated = $request->validate([
            'monto_pedido' => ['required', 'numeric', 'min:0.01'],
            'comentario_chofer' => ['nullable', 'string', 'max:1000'],
        ], [
            'monto_pedido.required' => 'El monto es obligatorio.',
            'monto_pedido.numeric' => 'El monto debe ser un valor numérico.',
            'monto_pedido.min' => 'El monto debe ser mayor a cero.',
        ]);

        DriverAdvanceRequest::create([
            'transportista_id' => $transportista->id,
            'monto_pedido' => $validated['monto_pedido'],
            'comentario_chofer' => $validated['comentario_chofer'] ?? null,
            'fecha_pedido' => now()->toDateString(),
            'estado' => DriverAdvanceRequest::ESTADO_PENDIENTE,
        ]);

        return redirect()
            ->route('portal-choferes.adelantos.index')
            ->with('ok', 'Solicitud de adelanto registrada correctamente.');
    }

    public function acceptCounterOffer(Request $request, DriverAdvanceRequest $advanceRequest): RedirectResponse
    {
        $transportista = $this->getTransportista($request);
        if (!$transportista || $advanceRequest->transportista_id !== $transportista->id) {
            abort(403, 'No tienes permiso sobre esta solicitud.');
        }

        if ($advanceRequest->estado !== DriverAdvanceRequest::ESTADO_CONTRAOFERTADO) {
            return back()->withErrors('La solicitud no tiene una contraoferta pendiente.');
        }

        $advanceRequest->update([
            'estado' => DriverAdvanceRequest::ESTADO_APROBADO,
            'fecha_resolucion' => now(),
        ]);

        return redirect()
            ->route('portal-choferes.adelantos.index')
            ->with('ok', 'Contraoferta aceptada y registrada correctamente.');
    }

    public function rejectCounterOffer(Request $request, DriverAdvanceRequest $advanceRequest): RedirectResponse
    {
        $transportista = $this->getTransportista($request);
        if (!$transportista || $advanceRequest->transportista_id !== $transportista->id) {
            abort(403, 'No tienes permiso sobre esta solicitud.');
        }

        if ($advanceRequest->estado !== DriverAdvanceRequest::ESTADO_CONTRAOFERTADO) {
            return back()->withErrors('La solicitud no tiene una contraoferta pendiente.');
        }

        $advanceRequest->update([
            'estado' => DriverAdvanceRequest::ESTADO_RECHAZADO,
            'fecha_resolucion' => now(),
        ]);

        return redirect()
            ->route('portal-choferes.adelantos.index')
            ->with('ok', 'Contraoferta rechazada.');
    }
}
