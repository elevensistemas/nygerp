<?php

namespace App\Http\Controllers;

use App\Models\Bank;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class BankController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search', ''));

        $banks = Bank::query()
            ->when($search, function ($query) use ($search) {
                $query->where('name', 'like', '%' . $search . '%');
            })
            ->withCount('transportistas')
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('traffic.banks.index', [
            'banks' => $banks,
            'search' => $search,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedData($request);
        $data['is_active'] = $request->boolean('is_active', true);

        Bank::create($data);

        return redirect()->route('traffic.banks.index')->with('ok', 'Banco creado.');
    }

    public function update(Request $request, Bank $bank): RedirectResponse
    {
        $data = $this->validatedData($request, $bank);
        $data['is_active'] = $request->boolean('is_active', true);

        $bank->update($data);

        return redirect()->route('traffic.banks.index')->with('ok', 'Banco actualizado.');
    }

    public function destroy(Bank $bank): RedirectResponse
    {
        if ($bank->transportistas()->exists()) {
            return redirect()
                ->route('traffic.banks.index')
                ->withErrors('No se puede eliminar el banco porque tiene transportistas asociados.');
        }

        $bank->delete();

        return redirect()->route('traffic.banks.index')->with('ok', 'Banco eliminado.');
    }

    private function validatedData(Request $request, ?Bank $bank = null): array
    {
        $uniqueName = Rule::unique('banks', 'name');
        if ($bank) {
            $uniqueName = $uniqueName->ignore($bank->id);
        }

        return $request->validate([
            'name' => ['required', 'string', 'max:120', $uniqueName],
            'is_active' => ['sometimes', 'boolean'],
        ]);
    }
}

