<?php

namespace App\Http\Controllers;

use App\Models\DeliveryReason;
use Illuminate\Http\Request;

class DeliveryReasonController extends Controller
{
    public function index(Request $request)
    {
        $editing = null;
        if ($request->filled('edit')) {
            $editingId = (int) $request->query('edit');
            $editing = $editingId ? DeliveryReason::find($editingId) : null;
        }

        return view('traffic.delivery-reasons.index', [
            'reasons' => DeliveryReason::orderBy('order_index')->orderBy('name')->paginate(15),
            'editing' => $editing,
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        DeliveryReason::create($data);

        return redirect()
            ->route('traffic.delivery-reasons.index')
            ->with('ok', 'Motivo creado.');
    }

    public function update(Request $request, DeliveryReason $deliveryReason)
    {
        $data = $this->validated($request, $deliveryReason->id);
        $deliveryReason->update($data);

        return redirect()
            ->route('traffic.delivery-reasons.index')
            ->with('ok', 'Motivo actualizado.');
    }

    public function destroy(DeliveryReason $deliveryReason)
    {
        $deliveryReason->delete();

        return redirect()
            ->route('traffic.delivery-reasons.index')
            ->with('ok', 'Motivo eliminado.');
    }

    private function validated(Request $request, int $ignoreId = 0): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:190', "unique:delivery_reasons,name,{$ignoreId}"],
            'order_index' => ['required', 'integer', 'min:0'],
            'color' => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ]);
    }
}
