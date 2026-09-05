<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\PaymentMethod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PaymentMethodController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search', ''));

        $methods = PaymentMethod::with('account')
            ->when($search, function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%");
                });
            })
            ->orderBy('code')
            ->paginate(20)
            ->withQueryString();

        $accounts = Account::orderBy('code')->get(['id', 'code', 'name']);

        return view('payment_methods.index', [
            'methods' => $methods,
            'accounts' => $accounts,
            'search' => $search,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $payload = $this->validatedData($request);
        $payload['active'] = $request->boolean('active', true);

        PaymentMethod::create($payload);

        return redirect()->route('payment-methods.index')->with('ok', 'Medio de pago creado.');
    }

    public function update(Request $request, PaymentMethod $paymentMethod): RedirectResponse
    {
        $payload = $this->validatedData($request, $paymentMethod->id);
        $payload['active'] = $request->boolean('active', true);

        $paymentMethod->update($payload);

        return redirect()->route('payment-methods.index')->with('ok', 'Medio de pago actualizado.');
    }

    public function show(PaymentMethod $paymentMethod): JsonResponse
    {
        return new JsonResponse($paymentMethod->only([
            'code',
            'name',
            'description',
            'account_id',
            'active',
        ]));
    }

    public function destroy(PaymentMethod $paymentMethod): RedirectResponse
    {
        $paymentMethod->delete();

        return redirect()->route('payment-methods.index')->with('ok', 'Medio de pago eliminado.');
    }

    private function validatedData(Request $request, ?int $ignoreId = null): array
    {
        $codeRule = 'required|string|max:20|unique:payment_methods,code';
        if ($ignoreId) {
            $codeRule .= ',' . $ignoreId;
        }

        return $request->validate([
            'code' => $codeRule,
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
            'account_id' => 'required|exists:accounts,id',
            'active' => 'nullable|boolean',
        ]);
    }
}
