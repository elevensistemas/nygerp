<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Tax;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TaxController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search', ''));

        $taxes = Tax::with('account')
            ->when($search, function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%");
                });
            })
            ->orderBy('type')
            ->orderBy('code')
            ->paginate(20)
            ->withQueryString();

        $accounts = Account::orderBy('code')->get(['id', 'code', 'name']);

        return view('taxes.index', [
            'taxes' => $taxes,
            'accounts' => $accounts,
            'search' => $search,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $payload = $this->validatedData($request);
        $payload['active'] = $request->boolean('active', true);

        Tax::create($payload);

        return redirect()->route('taxes.index')->with('ok', 'Impuesto creado correctamente.');
    }

    public function update(Request $request, Tax $tax): RedirectResponse
    {
        $payload = $this->validatedData($request, $tax->id);
        $payload['active'] = $request->boolean('active', true);

        $tax->update($payload);

        return redirect()->route('taxes.index')->with('ok', 'Impuesto actualizado.');
    }

    public function show(Tax $tax): JsonResponse
    {
        return new JsonResponse($tax->only([
            'code',
            'name',
            'type',
            'applies_on',
            'base',
            'rate',
            'account_id',
            'active',
        ]));
    }

    public function destroy(Tax $tax): RedirectResponse
    {
        $tax->delete();

        return redirect()->route('taxes.index')->with('ok', 'Impuesto eliminado.');
    }

    private function validatedData(Request $request, ?int $ignoreId = null): array
    {
        $codeRule = 'required|string|max:20|unique:taxes,code';
        if ($ignoreId) {
            $codeRule .= ',' . $ignoreId;
        }

        return $request->validate([
            'code' => $codeRule,
            'name' => 'required|string|max:255',
            'type' => 'required|in:perception,retention',
            'applies_on' => 'required|in:invoice,payment',
            'base' => 'required|in:net,gross',
            'rate' => 'required|numeric|min:0',
            'account_id' => 'required|exists:accounts,id',
            'active' => 'nullable|boolean',
        ]);
    }
}
