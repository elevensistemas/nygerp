<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\CostCenter;

class CostCenterController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->query('search', ''));

        $centers = CostCenter::query()
            ->when($search, function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%");
                });
            })
            ->orderBy('code')
            ->paginate(30)
            ->withQueryString();

        return view('cost_centers.index', [
            'centers' => $centers,
            'search' => $search,
        ]);
    }

    public function store(Request $request)
    {
        $payload = $this->validatedData($request);
        $center = CostCenter::create($payload);

        if ($request->wantsJson()) {
            return response()->json(['cost_center' => $center], 201);
        }

        return back()->with('ok', 'Centro de costo creado');
    }

    public function edit(CostCenter $costCenter)
    {
        return response()->json($costCenter);
    }

    public function update(Request $request, CostCenter $costCenter)
    {
        $payload = $this->validatedData($request, $costCenter->id);
        $costCenter->update($payload);

        if ($request->wantsJson()) {
            return response()->json(['ok' => true, 'cost_center' => $costCenter->fresh()]);
        }

        return back()->with('ok', 'Centro de costo actualizado');
    }

    public function destroy(CostCenter $costCenter)
    {
        $costCenter->delete();
        return back()->with('ok', 'Centro de costo eliminado');
    }

    private function validatedData(Request $request, ?int $ignoreId = null): array
    {
        $uniqueRule = 'unique:cost_centers,code';
        if ($ignoreId) {
            $uniqueRule .= ",{$ignoreId}";
        }

        return $request->validate([
            'code' => "required|string|max:50|{$uniqueRule}",
            'name' => 'required|string|max:255',
        ]);
    }
}
