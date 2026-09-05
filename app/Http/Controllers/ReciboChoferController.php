<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReciboChoferUpdateRequest;
use App\Models\DriverPaymentConcept;
use App\Models\ReciboChofer;
use App\Models\Transportista;
use App\Services\DriverPayments\ReceiptRecalculator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class ReciboChoferController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', ReciboChofer::class);

        $filters = $this->extractFilters($request);
        $query = ReciboChofer::query()
            ->with(['transportista:id,name', 'paymentFleet:id,name'])
            ->withCount('planillaLinks')
            ->orderByDesc('fecha_emision')
            ->orderByDesc('id');
        $this->applyFilters($query, $filters);

        $perPage = (int) $request->input('per_page', 20);
        $perPage = in_array($perPage, [10, 20, 50, 100], true) ? $perPage : 20;

        $zonasOptions = \App\Models\TrafficZone::query()->orderBy('name')->pluck('name')->toArray();

        return view('pago-choferes.recibos.index', [
            'recibos' => $query->paginate($perPage)->withQueryString(),
            'perPage' => $perPage,
            'transportistas' => Transportista::orderBy('name')->get(['id', 'name']),
            'zonasOptions' => $zonasOptions,
            'filters' => $filters,
            'states' => [
                ReciboChofer::ESTADO_CARGADO,
                ReciboChofer::ESTADO_EN_PLANILLA,
                ReciboChofer::ESTADO_PAGADO,
                ReciboChofer::ESTADO_PENDIENTE_PAGO,
                ReciboChofer::ESTADO_ANULADO,
            ],
        ]);
    }

    public function destroy(Request $request, ReciboChofer $recibo): RedirectResponse
    {
        $this->authorize('viewAny', ReciboChofer::class);

        if (! $this->isDeletableImported($recibo)) {
            return back()->with('info', 'Solo se pueden eliminar recibos importados de Excel en estado cargado y sin planilla.');
        }

        $recibo->items()->delete();
        $recibo->delete();

        return back()->with('ok', 'Recibo eliminado.');
    }

    public function bulkDestroy(Request $request): RedirectResponse
    {
        $this->authorize('viewAny', ReciboChofer::class);

        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:recibos_chofer,id'],
        ]);

        $ids = array_values(array_unique(array_map('intval', $data['ids'])));
        $deleted = 0;

        DB::transaction(function () use ($ids, &$deleted) {
            $recibos = ReciboChofer::whereIn('id', $ids)->lockForUpdate()->get();
            foreach ($recibos as $recibo) {
                if (! $this->isDeletableImported($recibo)) {
                    continue;
                }
                $recibo->items()->delete();
                $recibo->delete();
                $deleted++;
            }
        });

        if ($deleted === 0) {
            return back()->with('info', 'No se pudo eliminar ningun recibo. Verifica que esten en estado cargado.');
        }

        return back()->with('ok', 'Recibos eliminados: ' . $deleted . '.');
    }

    public function destroyAllCargados(Request $request): RedirectResponse
    {
        $this->authorize('viewAny', ReciboChofer::class);

        $filters = $this->extractFilters($request);
        $query = ReciboChofer::query();
        $this->applyFilters($query, $filters);

        $ids = $query
            ->where('estado', ReciboChofer::ESTADO_CARGADO)
            ->where('origen', 'excel_trafico')
            ->whereDoesntHave('planillaLinks')
            ->pluck('id');

        if ($ids->isEmpty()) {
            return back()->with('info', 'No hay recibos cargados elegibles para eliminar con los filtros actuales.');
        }

        \App\Models\ReciboChoferItem::whereIn('recibo_chofer_id', $ids)->delete();
        $deleted = ReciboChofer::whereIn('id', $ids)->delete();

        return back()->with('ok', 'Recibos cargados eliminados: ' . $deleted . '.');
    }

    public function show(ReciboChofer $recibo): View
    {
        $this->authorize('view', $recibo);
        $recibo->load(['transportista.defaultPaymentMethod.bank', 'transportista.liquidationMeta', 'paymentFleet.transportistas', 'items']);

        return view('pago-choferes.recibos.show', [
            'recibo' => $recibo,
            'conceptOptions' => DriverPaymentConcept::query()
                ->where('active', true)
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }

    public function update(ReciboChoferUpdateRequest $request, ReciboChofer $recibo): RedirectResponse
    {
        $this->authorize('update', $recibo);

        $data = $request->validated();

        if (isset($data['estado']) && $recibo->estado === ReciboChofer::ESTADO_PAGADO && $data['estado'] !== ReciboChofer::ESTADO_PAGADO) {
            abort(403, 'Un recibo pagado solo puede volver atras por admin.');
        }

        if ($request->hasFile('factura_pdf')) {
            if (! empty($recibo->factura_pdf_path)) {
                if (Storage::disk('local')->exists($recibo->factura_pdf_path)) {
                    Storage::disk('local')->delete($recibo->factura_pdf_path);
                } elseif (Storage::disk('public')->exists($recibo->factura_pdf_path)) {
                    Storage::disk('public')->delete($recibo->factura_pdf_path);
                }
            }

            $file = $request->file('factura_pdf');
            $filename = uniqid('factura_') . '_' . $file->getClientOriginalName();
            $storedPath = $file->storeAs('facturas_choferes', $filename, 'local');
            $recibo->factura_id = null;
            $recibo->factura_pdf_path = $storedPath;
            $recibo->factura_pdf_nombre = $file->getClientOriginalName();
            $recibo->factura_ref = $file->getClientOriginalName();
        }

        if (array_key_exists('observaciones', $data)) {
            $recibo->observaciones = $data['observaciones'];
        }

        if (array_key_exists('fecha_emision', $data)) {
            $recibo->fecha_emision = $data['fecha_emision'];
        }

        if (array_key_exists('plaza', $data)) {
            $recibo->plaza = $data['plaza'];
        }

        if (array_key_exists('factura_ref', $data)) {
            $recibo->factura_ref = $data['factura_ref'];
        }

        if (array_key_exists('factura_fecha', $data)) {
            $recibo->factura_fecha = $data['factura_fecha'];
        }

        if (array_key_exists('factura_observaciones', $data)) {
            $recibo->factura_observaciones = $data['factura_observaciones'];
        }

        $recibo->updated_by = optional($request->user())->id;

        if (! empty($data['estado'])) {
            $recibo->estado = $data['estado'];
        }

        $recibo->save();

        return redirect()->route('pago-choferes.recibos.show', $recibo)->with('ok', 'Recibo actualizado.');
    }

    public function anular(Request $request, ReciboChofer $recibo): RedirectResponse
    {
        $this->authorize('anular', $recibo);

        $recibo->estado = ReciboChofer::ESTADO_ANULADO;
        $recibo->updated_by = optional($request->user())->id;
        $recibo->save();

        return redirect()->route('pago-choferes.recibos.show', $recibo)->with('ok', 'Recibo anulado.');
    }

    public function unlinkInvoice(Request $request, ReciboChofer $recibo): RedirectResponse
    {
        $this->authorize('update', $recibo);

        if (! empty($recibo->factura_pdf_path)) {
            if (Storage::disk('local')->exists($recibo->factura_pdf_path)) {
                Storage::disk('local')->delete($recibo->factura_pdf_path);
            } elseif (Storage::disk('public')->exists($recibo->factura_pdf_path)) {
                Storage::disk('public')->delete($recibo->factura_pdf_path);
            }
        }

        $recibo->factura_id = null;
        $recibo->factura_ref = null;
        $recibo->factura_pdf_path = null;
        $recibo->factura_pdf_nombre = null;
        $recibo->factura_fecha = null;
        $recibo->factura_observaciones = null;
        $recibo->updated_by = optional($request->user())->id;
        $recibo->save();

        return redirect()->route('pago-choferes.recibos.show', $recibo)->with('ok', 'Factura desvinculada.');
    }

    public function recalculate(Request $request, ReciboChofer $recibo, ReceiptRecalculator $recalculator): RedirectResponse
    {
        $this->authorize('update', $recibo);

        if (! $this->isRecalculableImported($recibo)) {
            return redirect()
                ->route('pago-choferes.recibos.show', $recibo)
                ->with('info', 'Solo se pueden recalcular recibos importados de Excel en estado cargado y sin planilla.');
        }

        $recalculated = $recalculator->recalculate($recibo, $request->user());

        return redirect()
            ->route('pago-choferes.recibos.show', $recibo)
            ->with('ok', 'Recibo recalculado. Lineas actualizadas: ' . $recalculated . '.');
    }

    public function print(ReciboChofer $recibo): View
    {
        $this->authorize('view', $recibo);
        $recibo->load(['transportista', 'paymentFleet', 'items']);

        return view('pago-choferes.recibos.print', ['recibo' => $recibo]);
    }

    public function downloadInvoice(ReciboChofer $recibo)
    {
        $this->authorize('view', $recibo);

        if (empty($recibo->factura_pdf_path)) {
            abort(404, 'Factura no encontrada.');
        }

        if (Storage::disk('local')->exists($recibo->factura_pdf_path)) {
            return Storage::disk('local')->response($recibo->factura_pdf_path, $recibo->factura_pdf_nombre);
        } elseif (Storage::disk('public')->exists($recibo->factura_pdf_path)) {
            return Storage::disk('public')->response($recibo->factura_pdf_path, $recibo->factura_pdf_nombre);
        }

        abort(404, 'El archivo físico de la factura no existe.');
    }

    private function extractFilters(Request $request): array
    {
        $zonaArray = array_values(array_filter(array_map('trim', (array) $request->input('zona', $request->query('zona', [])))));

        return [
            'tipo_periodo' => $request->input('tipo_periodo', $request->query('tipo_periodo')),
            'desde' => $request->input('desde', $request->query('desde')),
            'hasta' => $request->input('hasta', $request->query('hasta')),
            'transportista_id' => array_values(array_filter(array_map('intval', (array) $request->input('transportista_id', $request->query('transportista_id', []))))),
            'plaza' => $request->input('plaza', $request->query('plaza')),
            'zona' => $zonaArray,
            'estado' => $request->input('estado', $request->query('estado')),
            'search' => $request->input('search', $request->query('search')),
        ];
    }

    private function applyFilters($query, array $filters): void
    {
        if (! empty($filters['tipo_periodo'])) {
            $query->where('tipo_periodo', $filters['tipo_periodo']);
        }

        if (! empty($filters['estado'])) {
            $query->where('estado', $filters['estado']);
        }

        if (! empty($filters['transportista_id'])) {
            $query->whereIn('transportista_id', $filters['transportista_id']);
        }

        if (! empty($filters['desde']) || ! empty($filters['hasta'])) {
            $desde = ! empty($filters['desde']) ? $filters['desde'] : null;
            $hasta = ! empty($filters['hasta']) ? $filters['hasta'] : null;

            $query->where(function ($q) use ($desde, $hasta) {
                // Header emission date matches range
                $q->where(function ($sub) use ($desde, $hasta) {
                    if ($desde) {
                        $sub->whereDate('fecha_emision', '>=', $desde);
                    }
                    if ($hasta) {
                        $sub->whereDate('fecha_emision', '<=', $hasta);
                    }
                })
                // Item date matches range
                ->orWhereHas('items', function ($sub) use ($desde, $hasta) {
                    if ($desde) {
                        $sub->whereDate('meta->fecha', '>=', $desde);
                    }
                    if ($hasta) {
                        $sub->whereDate('meta->fecha', '<=', $hasta);
                    }
                });
            });
        }

        if (! empty($filters['plaza'])) {
            $plaza = trim((string) $filters['plaza']);
            $query->where(function ($q) use ($plaza) {
                $q->where('plaza', 'like', '%' . $plaza . '%')
                  ->orWhereHas('items', function ($sub) use ($plaza) {
                      $sub->where('zona', 'like', '%' . $plaza . '%')
                          ->orWhere('meta', 'like', '%' . $plaza . '%');
                  });
            });
        }

        if (! empty($filters['zona'])) {
            $zonas = (array) $filters['zona'];
            $query->where(function ($q) use ($zonas) {
                foreach ($zonas as $z) {
                    $zTrim = trim((string) $z);
                    if ($zTrim === '') continue;
                    $q->orWhere('plaza', 'like', '%' . $zTrim . '%')
                      ->orWhereHas('items', function ($sub) use ($zTrim) {
                          $sub->where('zona', 'like', '%' . $zTrim . '%')
                              ->orWhere('meta', 'like', '%' . $zTrim . '%');
                      });
                }
            });
        }

        if (! empty($filters['search'])) {
            $search = trim((string) $filters['search']);
            $query->where(function ($q) use ($search) {
                $q->where('id', $search)
                  ->orWhereHas('transportista', function ($sub) use ($search) {
                      $sub->where('name', 'like', '%' . $search . '%');
                  })
                  ->orWhereHas('items', function ($sub) use ($search) {
                      $sub->where('concepto', 'like', '%' . $search . '%')
                          ->orWhere('meta', 'like', '%' . $search . '%');
                  });
            });
        }
    }

    private function isDeletableImported(ReciboChofer $recibo): bool
    {
        return $this->isRecalculableImported($recibo);
    }

    private function isRecalculableImported(ReciboChofer $recibo): bool
    {
        if ($recibo->estado !== ReciboChofer::ESTADO_CARGADO) {
            return false;
        }

        if ($recibo->origen !== 'excel_trafico') {
            return false;
        }

        if ((int) ($recibo->planilla_links_count ?? 0) > 0) {
            return false;
        }

        return ! $recibo->planillaLinks()->exists();
    }
}
