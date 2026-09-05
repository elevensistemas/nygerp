<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\CostCenter;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search', ''));
        $type = $request->query('type');

        $products = Product::query()
            ->when($search, function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%");
                });
            })
            ->when($type, fn ($query) => $query->where('type', $type))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        if ($request->wantsJson()) {
            return new JsonResponse([
                'products' => $products->items(),
                'meta' => [
                    'total' => $products->total(),
                    'per_page' => $products->perPage(),
                    'current_page' => $products->currentPage(),
                ],
            ]);
        }

        return view('products.index', [
            'products' => $products,
            'search' => $search,
            'type' => $type,
            'accounts' => Account::orderBy('code')->get(['id', 'code', 'name']),
            'centers' => CostCenter::orderBy('code')->get(['id', 'code', 'name']),
        ]);
    }

    public function store(Request $request)
    {
        $payload = $this->validatedData($request);
        $product = Product::create($payload);

        if ($request->wantsJson()) {
            return new JsonResponse(['product' => $product->fresh()], 201);
        }

        return redirect()->route('products.index')->with('ok', 'Producto creado');
    }

    public function edit(Product $product): JsonResponse
    {
        return new JsonResponse($product);
    }

    public function update(Request $request, Product $product)
    {
        $payload = $this->validatedData($request, $product->id);
        $product->update($payload);

        if ($request->wantsJson()) {
            return new JsonResponse(['ok' => true, 'product' => $product->fresh()]);
        }

        return redirect()->route('products.index')->with('ok', 'Producto actualizado');
    }

    public function destroy(Product $product): RedirectResponse
    {
        $product->delete();

        return redirect()->route('products.index')->with('ok', 'Producto eliminado');
    }

    private function validatedData(Request $request, ?int $ignoreId = null): array
    {
        $codeRule = 'required|string|max:30|unique:products,code';
        if ($ignoreId) {
            $codeRule .= ",{$ignoreId}";
        }

        return $request->validate([
            'code' => $codeRule,
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
            'type' => 'required|in:goods,service,other',
            'unit' => 'nullable|string|max:25',
            'default_price' => 'nullable|numeric|min:0',
            'iva_rate' => 'nullable|numeric|min:0|max:100',
            'default_account_id' => 'nullable|exists:accounts,id',
            'default_cost_center_id' => 'nullable|exists:cost_centers,id',
            'is_active' => 'nullable|boolean',
        ]) + [
            'is_active' => $request->boolean('is_active', true),
        ];
    }
}
