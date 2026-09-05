<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReciboChoferItemStoreRequest;
use App\Http\Requests\ReciboChoferItemUpdateRequest;
use App\Models\DriverPaymentConcept;
use App\Models\ReciboChofer;
use App\Models\ReciboChoferItem;
use Illuminate\Http\RedirectResponse;

class ReciboChoferItemController extends Controller
{
    public function store(ReciboChoferItemStoreRequest $request, ReciboChofer $recibo): RedirectResponse
    {
        $this->authorize('updateItem', $recibo);

        $data = $request->validated();
        $cantidad = array_key_exists('cantidad', $data) && $data['cantidad'] !== null && $data['cantidad'] !== ''
            ? (float) $data['cantidad']
            : null;
        $importe = $this->applyConceptSign((string) $data['concepto'], (float) $data['importe']);

        ReciboChoferItem::create([
            'recibo_chofer_id' => $recibo->id,
            'concepto' => $data['concepto'],
            'cantidad' => $cantidad,
            'importe_unitario' => $cantidad && $cantidad != 0.0 ? round($importe / $cantidad, 2) : $importe,
            'importe' => $importe,
            'meta' => [
                'manual' => true,
            ],
            'source_key' => null,
        ]);

        $recibo->recalculateTotal();

        return redirect()->route('pago-choferes.recibos.show', $recibo)->with('ok', 'Item agregado.');
    }

    public function update(ReciboChoferItemUpdateRequest $request, ReciboChoferItem $item): RedirectResponse
    {
        $recibo = $item->recibo;
        $this->authorize('updateItem', $recibo);

        $data = $request->validated();

        $unitario = $data['importe_unitario'] ?? null;

        $item->concepto = $data['concepto'];
        $item->importe_unitario = ($unitario !== null && $unitario !== '')
            ? $this->applyConceptSign((string) $data['concepto'], (float) $unitario)
            : null;
        if (array_key_exists('cantidad', $data)) {
            $item->cantidad = ($data['cantidad'] !== null && $data['cantidad'] !== '')
                ? $data['cantidad']
                : null;
        }

        if ($data['importe'] !== null && $data['importe'] !== '') {
            $item->importe = $this->applyConceptSign((string) $item->concepto, (float) $data['importe']);
            if (($unitario === null || $unitario === '') && $item->cantidad !== null && (float) $item->cantidad !== 0.0) {
                $item->importe_unitario = round((float) $item->importe / (float) $item->cantidad, 2);
            }
        } elseif ($unitario !== null && $unitario !== '') {
            $item->importe = $this->applyConceptSign((string) $item->concepto, (float) $unitario);
        }

        $item->save();

        $recibo->recalculateTotal();

        return redirect()->route('pago-choferes.recibos.show', $recibo)->with('ok', 'Item actualizado.');
    }

    public function destroy(ReciboChoferItem $item): RedirectResponse
    {
        $recibo = $item->recibo;
        $this->authorize('updateItem', $recibo);

        $item->delete();

        $recibo->recalculateTotal();

        return redirect()->route('pago-choferes.recibos.show', $recibo)->with('ok', 'Item eliminado.');
    }

    private function applyConceptSign(string $conceptName, float $amount): float
    {
        $concept = DriverPaymentConcept::query()
            ->whereRaw('LOWER(name) = ?', [mb_strtolower(trim($conceptName))])
            ->first();

        if (! $concept || (int) $concept->sign !== -1) {
            return $amount;
        }

        return abs($amount) * -1;
    }
}
