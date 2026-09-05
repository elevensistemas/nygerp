<?php

namespace App\Http\Controllers;

use App\Models\PaymentTerm;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PaymentTermController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search', ''));

        $terms = PaymentTerm::query()
            ->when($search, fn ($query) => $query->where('name', 'like', "%{$search}%"))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('payment_terms.index', [
            'terms' => $terms,
            'search' => $search,
        ]);
    }

    public function store(Request $request)
    {
        $payload = $this->validatedData($request);
        $term = PaymentTerm::create($payload);

        if ($request->wantsJson()) {
            return new JsonResponse(['term' => $term], 201);
        }

        return redirect()->route('payment-terms.index')->with('ok', 'Condición creada');
    }

    public function edit(PaymentTerm $paymentTerm): JsonResponse
    {
        return new JsonResponse($paymentTerm);
    }

    public function update(Request $request, PaymentTerm $paymentTerm)
    {
        $payload = $this->validatedData($request);
        $paymentTerm->update($payload);

        if ($request->wantsJson()) {
            return new JsonResponse(['ok' => true, 'term' => $paymentTerm->fresh()]);
        }

        return redirect()->route('payment-terms.index')->with('ok', 'Condición actualizada');
    }

    public function destroy(PaymentTerm $paymentTerm): RedirectResponse
    {
        $paymentTerm->delete();

        return redirect()->route('payment-terms.index')->with('ok', 'Condición eliminada');
    }

    private function validatedData(Request $request): array
    {
        $validated = $request->validate([
            'name' => 'required|string|max:120',
            'days' => 'nullable|string|max:120',
        ]);

        $days = $this->parseDays($validated['days'] ?? '');

        return [
            'name' => $validated['name'],
            'days' => !empty($days) ? $days : null,
        ];
    }

    private function parseDays(?string $raw): array
    {
        if (!$raw) {
            return [];
        }

        return collect(explode(',', $raw))
            ->map(fn ($value) => trim($value))
            ->filter(fn ($value) => $value !== '' && is_numeric($value))
            ->map(fn ($value) => max(0, (int) $value))
            ->unique()
            ->sort()
            ->values()
            ->all();
    }
}
