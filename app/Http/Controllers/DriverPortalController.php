<?php

namespace App\Http\Controllers;

use App\Models\ReciboChofer;
use App\Models\ReciboChoferItem;
use App\Models\DriverPaymentItemLog;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;

class DriverPortalController extends Controller
{
    private function getAccessibleTransportistaIds(Request $request): array
    {
        $user = $request->user();
        if ($user->isAdminOrSuper()) {
            return []; // empty array means all
        }

        if ($user->isTransportista()) {
            $transportista = $user->transportistaProfile;
            if (!$transportista) {
                return [-1];
            }
            if (!in_array($transportista->portal_visibility ?? 'all', ['all', 'settlements_only'])) {
                return [-1];
            }
            $transportistaIds = collect([$transportista->id]);
            $fleetBillingIds = \App\Models\DriverPaymentFleet::query()
                ->where('active', true)
                ->whereHas('transportistas', function ($query) use ($transportistaIds) {
                    $query->whereIn('transportistas.id', $transportistaIds->all());
                })
                ->pluck('billing_transportista_id')
                ->filter()
                ->map(fn ($id) => (int) $id);
            $allowed = $transportistaIds->concat($fleetBillingIds)->unique()->values()->all();
            if (!empty($allowed)) {
                return $allowed;
            }
        }

        return [-1]; // none
    }

    public function index(Request $request): View
    {
        $allowedIds = $this->getAccessibleTransportistaIds($request);
        if ($allowedIds === [-1]) {
            abort(403, 'No tienes perfil de transportista asociado o tu email no fue encontrado en ningun legajo de transporte.');
        }

        $desde = $request->input('desde', now()->subMonth()->startOfMonth()->format('Y-m-d'));
        $hasta = $request->input('hasta', now()->endOfMonth()->format('Y-m-d'));

        $query = ReciboChofer::query()
            ->with(['transportista', 'paymentFleet'])
            ->withCount('items')
            ->whereDate('fecha_emision', '>=', $desde)
            ->whereDate('fecha_emision', '<=', $hasta)
            ->orderByDesc('fecha_emision')
            ->orderByDesc('id');

        if (!empty($allowedIds)) {
            $query->whereIn('transportista_id', $allowedIds);
        }

        $perPage = 20;

        return view('portal-choferes.liquidaciones.index', [
            'recibos' => $query->paginate($perPage)->appends(['desde' => $desde, 'hasta' => $hasta]),
            'filters' => [
                'desde' => $desde,
                'hasta' => $hasta,
            ]
        ]);
    }

    public function show(Request $request, ReciboChofer $recibo): View
    {
        $allowedIds = $this->getAccessibleTransportistaIds($request);
        if ($allowedIds === [-1] || (!empty($allowedIds) && !in_array($recibo->transportista_id, $allowedIds))) {
            abort(403, 'No tienes permiso para ver esta liquidación.');
        }

        $recibo->load(['transportista', 'paymentFleet', 'items.logs.user']);

        return view('portal-choferes.liquidaciones.show', [
            'recibo' => $recibo,
        ]);
    }

    public function updateItemStatus(Request $request, ReciboChoferItem $item): RedirectResponse
    {
        $recibo = $item->recibo;
        $allowedIds = $this->getAccessibleTransportistaIds($request);
        if ($allowedIds === [-1] || (!empty($allowedIds) && !in_array($recibo->transportista_id, $allowedIds))) {
            abort(403, 'No tienes permiso para modificar esta liquidación.');
        }

        $data = $request->validate([
            'status' => ['required', 'string', 'in:confirmado,disputado'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($data['status'] === 'disputado' && empty($data['comment'])) {
            return back()->with('info', 'Debes ingresar un comentario al informar una discrepancia.');
        }

        $item->driver_status = $data['status'];
        $item->driver_comment = $data['comment'] ?? null;
        $item->driver_status_date = now();
        $item->save();

        DriverPaymentItemLog::create([
            'recibo_chofer_item_id' => $item->id,
            'status' => $data['status'],
            'comment' => $data['comment'] ?? null,
            'user_id' => $request->user()->id,
        ]);

        return back()->with('ok', 'Estado de la línea actualizado.');
    }
    public function submitContactRequest(Request $request, ReciboChofer $recibo): RedirectResponse
    {
        $allowedIds = $this->getAccessibleTransportistaIds($request);
        if ($allowedIds === [-1] || (!empty($allowedIds) && !in_array($recibo->transportista_id, $allowedIds))) {
            abort(403, 'No tienes permiso para modificar esta liquidacion.');
        }

        $data = $request->validate([
            'comment' => ['required', 'string', 'max:1500'],
        ]);

        $recibo->driver_contact_request_comment = trim((string) $data['comment']);
        $recibo->driver_contact_request_date = now();
        $recibo->save();

        return back()->with('ok', 'Solicitud enviada. Nos contactaremos para revisar faltantes o dias no liquidados.');
    }

    public function uploadInvoice(Request $request, ReciboChofer $recibo): RedirectResponse
    {
        $allowedIds = $this->getAccessibleTransportistaIds($request);
        if ($allowedIds === [-1] || (!empty($allowedIds) && !in_array($recibo->transportista_id, $allowedIds))) {
            abort(403, 'No tienes permiso para modificar esta liquidación.');
        }

        if ($recibo->estado === ReciboChofer::ESTADO_PAGADO) {
            return back()->with('error', 'La liquidación ya fue pagada y la factura no puede ser modificada.');
        }

        $request->validate([
            'facturas' => ['required', 'array', 'min:1'],
            'facturas.*' => ['required', 'file', 'mimes:pdf', 'max:10240'],
        ]);

        $adjuntos = $recibo->facturas_adjuntas ?? [];

        foreach ($request->file('facturas') as $file) {
            $filename = uniqid('factura_') . '_' . $file->getClientOriginalName();
            $path = $file->storeAs('facturas_choferes', $filename, 'local');

            $adjuntos[] = [
                'path' => $path,
                'nombre' => $file->getClientOriginalName(),
                'uploaded_at' => now()->toDateTimeString()
            ];
        }

        $recibo->facturas_adjuntas = $adjuntos;
        if (count($adjuntos) > 0 && empty($recibo->factura_pdf_path)) {
            $recibo->factura_pdf_path = $adjuntos[0]['path'];
            $recibo->factura_pdf_nombre = $adjuntos[0]['nombre'];
        }
        $recibo->save();

        return back()->with('ok', 'Factura(s) subida(s) correctamente.');
    }

    public function downloadInvoice(Request $request, ReciboChofer $recibo)
    {
        $allowedIds = $this->getAccessibleTransportistaIds($request);
        if ($allowedIds === [-1] || (!empty($allowedIds) && !in_array($recibo->transportista_id, $allowedIds))) {
            abort(403, 'No tienes permiso para ver esta factura.');
        }

        $index = $request->query('index');
        $adjuntos = $recibo->facturas_adjuntas ?? [];

        if ($index !== null && isset($adjuntos[$index])) {
            $path = $adjuntos[$index]['path'];
            $nombre = $adjuntos[$index]['nombre'];
        } else {
            if (empty($recibo->factura_pdf_path)) {
                abort(404, 'Factura no encontrada.');
            }
            $path = $recibo->factura_pdf_path;
            $nombre = $recibo->factura_pdf_nombre;
        }

        if (Storage::disk('local')->exists($path)) {
            return Storage::disk('local')->response($path, $nombre);
        } elseif (Storage::disk('public')->exists($path)) {
            return Storage::disk('public')->response($path, $nombre);
        }

        abort(404, 'El archivo físico de la factura no existe.');
    }

    public function deleteInvoice(Request $request, ReciboChofer $recibo): RedirectResponse
    {
        $allowedIds = $this->getAccessibleTransportistaIds($request);
        if ($allowedIds === [-1] || (!empty($allowedIds) && !in_array($recibo->transportista_id, $allowedIds))) {
            abort(403, 'No tienes permiso para modificar esta liquidación.');
        }

        if ($recibo->estado === ReciboChofer::ESTADO_PAGADO) {
            return back()->with('error', 'La liquidación ya fue pagada y las facturas no pueden ser eliminadas.');
        }

        $index = $request->input('index');
        $adjuntos = $recibo->facturas_adjuntas ?? [];

        if ($index !== null && isset($adjuntos[$index])) {
            $path = $adjuntos[$index]['path'];
            if (Storage::disk('local')->exists($path)) {
                Storage::disk('local')->delete($path);
            } elseif (Storage::disk('public')->exists($path)) {
                Storage::disk('public')->delete($path);
            }
            unset($adjuntos[$index]);
            $recibo->facturas_adjuntas = array_values($adjuntos);
            
            if (empty($recibo->facturas_adjuntas)) {
                $recibo->factura_pdf_path = null;
                $recibo->factura_pdf_nombre = null;
            } else {
                $recibo->factura_pdf_path = $recibo->facturas_adjuntas[0]['path'];
                $recibo->factura_pdf_nombre = $recibo->facturas_adjuntas[0]['nombre'];
            }
            $recibo->save();

            return back()->with('ok', 'Factura eliminada correctamente.');
        }

        return back()->with('error', 'No se encontró la factura a eliminar.');
    }
}
