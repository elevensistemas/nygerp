<?php

namespace App\Http\Controllers;

use App\Models\Party;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search', ''));

        $customers = Party::query()
            ->where('role', 'customer')
            ->when($search, function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('name', 'like', "%{$search}%")
                        ->orWhere('business_name', 'like', "%{$search}%")
                        ->orWhere('tax_id', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('customers.index', [
            'customers' => $customers,
            'search' => $search,
        ]);
    }

    public function store(Request $request)
    {
        $payload = $this->validatedData($request);
        $payload['role'] = 'customer';
        $payload['active'] = $request->boolean('active', true);

        $customer = Party::create($payload);

        if ($request->wantsJson()) {
            return new JsonResponse(['customer' => $customer], 201);
        }

        return redirect()->route('customers.index')->with('ok', 'Cliente creado');
    }

    public function edit(Party $customer): JsonResponse
    {
        abort_unless($customer->role === 'customer', 404);

        return new JsonResponse($customer);
    }

    public function update(Request $request, Party $customer)
    {
        abort_unless($customer->role === 'customer', 404);

        $payload = $this->validatedData($request, $customer->id);
        $payload['active'] = $request->boolean('active', true);

        $customer->update($payload);

        if ($request->wantsJson()) {
            return new JsonResponse(['ok' => true, 'customer' => $customer->fresh()], 200);
        }

        return redirect()->route('customers.index')->with('ok', 'Cliente actualizado');
    }

    public function destroy(Party $customer): RedirectResponse
    {
        abort_unless($customer->role === 'customer', 404);
        $customer->delete();

        return redirect()->route('customers.index')->with('ok', 'Cliente eliminado');
    }

    private function validatedData(Request $request, ?int $ignoreId = null): array
    {
        $taxIdRule = 'nullable|string|max:15';
        if ($ignoreId) {
            $taxIdRule .= "|unique:parties,tax_id,{$ignoreId}";
        } else {
            $taxIdRule .= '|unique:parties,tax_id';
        }

        return $request->validate([
            'name' => 'required|string|max:255',
            'business_name' => 'nullable|string|max:255',
            'tax_id' => $taxIdRule,
            'iva_condition' => 'nullable|in:responsable_inscripto,monotributo,exento,consumidor_final,no_residente',
            'iibb_number' => 'nullable|string|max:25',
            'email' => 'nullable|email|max:150',
            'email_secondary' => 'nullable|email|max:150',
            'phone' => 'nullable|string|max:30',
            'mobile' => 'nullable|string|max:30',
            'address' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:120',
            'province' => 'nullable|string|max:120',
            'postal_code' => 'nullable|string|max:12',
            'bank_alias' => 'nullable|string|max:50',
            'bank_cbu' => 'nullable|string|max:30',
            'notes' => 'nullable|string|max:500',
            'active' => 'nullable|boolean',
        ]);
    }
}
