<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Account;

class AccountController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->query('search', ''));

        $accounts = Account::query()
            ->when($search, function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhere('type', 'like', "%{$search}%");
                });
            })
            ->orderBy('code')
            ->paginate(30)
            ->withQueryString();

        return view('accounts.index', [
            'accounts' => $accounts,
            'search' => $search,
        ]);
    }

    public function store(Request $request)
    {
        $payload = $this->validatedData($request);
        $account = Account::create($payload);

        if ($request->wantsJson()) {
            return response()->json(['account' => $account], 201);
        }

        return back()->with('ok', 'Cuenta creada');
    }

    public function edit(Account $account)
    {
        return response()->json($account);
    }

    public function update(Request $request, Account $account)
    {
        $payload = $this->validatedData($request, $account->id);
        $account->update($payload);

        if ($request->wantsJson()) {
            return response()->json(['ok' => true, 'account' => $account->fresh()]);
        }

        return back()->with('ok', 'Cuenta actualizada');
    }

    public function destroy(Account $account)
    {
        $account->delete();
        return back()->with('ok', 'Cuenta eliminada');
    }

    private function validatedData(Request $request, ?int $ignoreId = null): array
    {
        $uniqueRule = 'unique:accounts,code';
        if ($ignoreId) {
            $uniqueRule .= ",{$ignoreId}";
        }

        return $request->validate([
            'code' => "required|string|max:50|{$uniqueRule}",
            'name' => 'required|string|max:255',
            'type' => 'required|in:asset,liability,equity,income,expense',
        ]);
    }
}
