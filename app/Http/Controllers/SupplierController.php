<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use App\Models\Tax;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SupplierController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search', ''));

        $suppliers = Supplier::query()
            ->when($search, function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('name', 'like', "%{$search}%")
                        ->orWhere('tax_id', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        $taxes = Tax::orderBy('name')->get(['id', 'code', 'name', 'type']);

        return view('suppliers.index', [
            'suppliers' => $suppliers,
            'search' => $search,
            'taxes' => $taxes,
        ]);
    }

    public function store(Request $request)
    {
        $payload = $this->validatedData($request);
        $taxIds = $this->validatedTaxIds($request);
        $payload['active'] = $request->boolean('active', true);

        $supplier = Supplier::create($payload);
        $supplier->taxes()->sync($taxIds);
        $supplier->load('taxes');

        if ($request->wantsJson()) {
            return new JsonResponse(['supplier' => $supplier], 201);
        }

        return redirect()->route('suppliers.index')->with('ok', 'Proveedor agregado correctamente');
    }

    public function edit(Supplier $supplier): JsonResponse
    {
        $supplier->load(['taxes' => function ($query) {
            $query
                ->select([
                    'taxes.id',
                    'taxes.code',
                    'taxes.name',
                    'taxes.type',
                    'taxes.applies_on',
                    'taxes.base',
                    'taxes.rate',
                    'taxes.account_id',
                    'taxes.active',
                ]);
        }]);

        return new JsonResponse([
            'supplier' => $supplier,
            'tax_ids'  => $supplier->taxes->pluck('id'),
            'taxes'    => $supplier->taxes->map(function (Tax $tax) {
                return [
                    'id' => $tax->id,
                    'code' => $tax->code,
                    'name' => $tax->name,
                    'type' => $tax->type,
                    'applies_on' => $tax->applies_on,
                    'base' => $tax->base,
                    'rate' => $tax->rate,
                    'active' => $tax->active,
                ];
            }),
        ]);
    }

    public function update(Request $request, Supplier $supplier)
    {
        $payload = $this->validatedData($request, $supplier->id);
        $taxIds = $this->validatedTaxIds($request);
        $payload['active'] = $request->boolean('active', true);

        $supplier->update($payload);
        $supplier->taxes()->sync($taxIds);
        $supplier->load('taxes');

        if ($request->wantsJson()) {
            return new JsonResponse(['ok' => true, 'supplier' => $supplier], 200);
        }

        return redirect()->route('suppliers.index')->with('ok', 'Proveedor actualizado');
    }

    public function destroy(Supplier $supplier): RedirectResponse
    {
        $supplier->delete();

        return redirect()->route('suppliers.index')->with('ok', 'Proveedor eliminado');
    }

    private function validatedData(Request $request, ?int $ignoreId = null): array
    {
        $taxRule = 'nullable|string|max:15';
        if ($ignoreId) {
            $taxRule .= "|unique:suppliers,tax_id,{$ignoreId}";
        } else {
            $taxRule .= '|unique:suppliers,tax_id';
        }

        return $request->validate([
            'name' => 'required|string|max:255',
            'tax_id' => $taxRule,
            'iva_condition' => 'nullable|in:responsable_inscripto,monotributo,exento,consumidor_final,no_residente',
            'iibb_number' => 'nullable|string|max:25',
            'address' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:120',
            'province' => 'nullable|string|max:120',
            'postal_code' => 'nullable|string|max:12',
            'phone' => 'nullable|string|max:30',
            'mobile' => 'nullable|string|max:30',
            'email' => 'nullable|email|max:150',
            'bank_alias' => 'nullable|string|max:50',
            'bank_cbu' => 'nullable|string|max:30',
            'active' => 'nullable|boolean',
        ]);
    }

    private function validatedTaxIds(Request $request): array
    {
        $validated = $request->validate([
            'tax_ids' => 'nullable|array',
            'tax_ids.*' => 'integer|exists:taxes,id',
        ]);

        return array_values($validated['tax_ids'] ?? []);
    }
}
