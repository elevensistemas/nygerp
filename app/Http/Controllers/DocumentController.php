<?php

namespace App\Http\Controllers;

use App\Models\{Document, Supplier, PaymentTerm, Account, CostCenter, Product};
use App\Services\DocumentService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DocumentController extends Controller
{
    /** Listado (acepta ?scope=sale|purchase) */
    public function index(Request $r)
    {
    // Forcing explicit separation: default to 'purchase' when no scope provided
    $scope = $r->query('scope') ?? 'purchase';
        $filters = [
            'from'        => $r->query('from'),
            'to'          => $r->query('to'),
            'supplier_id' => $r->query('supplier_id'),
            'search'      => trim((string) $r->query('search', '')) ?: null,
        ];

        $base = Document::query();
        $this->applyFilters($base, $scope, $filters);

        $documents = (clone $base)
            ->with(['supplier'])
            ->orderByDesc('issue_date')
            ->orderByDesc('id')
            ->paginate(20)
            ->appends(array_filter(
                ['scope' => $scope] + $filters,
                static fn ($value) => $value !== null && $value !== ''
            ));

        if ($scope === 'purchase') {
            $statsRow = (clone $base)
                ->selectRaw("COUNT(*) as count, COALESCE(SUM(CASE WHEN doctype IN ('invoice','debit_note') THEN subtotal ELSE -subtotal END),0) as subtotal_sum, COALESCE(SUM(CASE WHEN doctype IN ('invoice','debit_note') THEN tax ELSE -tax END),0) as tax_sum, COALESCE(SUM(CASE WHEN doctype IN ('invoice','debit_note') THEN total ELSE -total END),0) as total_sum")
                ->first();
        } else {
            $statsRow = (clone $base)
                ->selectRaw('COUNT(*) as count, COALESCE(SUM(subtotal),0) as subtotal_sum, COALESCE(SUM(tax),0) as tax_sum, COALESCE(SUM(total),0) as total_sum')
                ->first();
        }

        $summary = [
            'count'    => (int) ($statsRow->count ?? 0),
            'subtotal' => (float) ($statsRow->subtotal_sum ?? 0),
            'tax'      => (float) ($statsRow->tax_sum ?? 0),
            'total'    => (float) ($statsRow->total_sum ?? 0),
        ];
        $summary['average'] = $summary['count'] > 0 ? $summary['total'] / $summary['count'] : 0.0;

        $doctypeBreakdown = (clone $base)
            ->select('doctype', DB::raw('COUNT(*) as count'), DB::raw(
                $scope === 'purchase'
                    ? "SUM(CASE WHEN doctype IN ('invoice','debit_note') THEN total ELSE -total END) as total"
                    : 'SUM(total) as total'
            ))
            ->groupBy('doctype')
            ->orderByDesc('total')
            ->get()
            ->map(function ($row) {
                $label = Document::labelForDoctype($row->doctype);
                return (object) [
                    'doctype' => $row->doctype,
                    'label'   => $label,
                    'count'   => (int) $row->count,
                    'total'   => (float) $row->total,
                ];
            });

        $topSuppliers = collect();
        if ($scope === 'purchase') {
            $topSuppliers = Supplier::query()
                ->select(
                    'suppliers.id',
                    'suppliers.name',
                    DB::raw('COUNT(documents.id) as documents_count'),
                    DB::raw($scope === 'purchase'
                        ? "SUM(CASE WHEN documents.doctype IN ('invoice','debit_note') THEN documents.total ELSE -documents.total END) as total_sum"
                        : 'SUM(documents.total) as total_sum')
                )
                ->join('documents', 'documents.supplier_id', '=', 'suppliers.id')
                ->where('documents.scope', 'purchase')
                ->when($filters['from'], fn ($q, $date) => $q->whereDate('documents.issue_date', '>=', $date))
                ->when($filters['to'], fn ($q, $date) => $q->whereDate('documents.issue_date', '<=', $date))
                ->when($filters['search'], function ($q, $term) {
                    $q->where(function ($inner) use ($term) {
                        $inner->where('documents.number', 'like', "%{$term}%")
                            ->orWhere('documents.notes', 'like', "%{$term}%")
                            ->orWhere('suppliers.name', 'like', "%{$term}%");
                    });
                })
                ->when($filters['supplier_id'], fn ($q, $supplierId) => $q->where('suppliers.id', $supplierId))
                ->groupBy('suppliers.id', 'suppliers.name')
                ->orderByDesc('total_sum')
                ->limit(5)
                ->get();
        }

        $suppliers = $scope === 'purchase'
            ? Supplier::orderBy('name')->get(['id', 'name'])
            : collect();

        return view('documents.index', [
            'documents'        => $documents,
            'scope'            => $scope,
            'filters'          => $filters,
            'summary'          => $summary,
            'doctypeBreakdown' => $doctypeBreakdown,
            'topSuppliers'     => $topSuppliers,
            'suppliers'        => $suppliers,
        ]);
    }

    /** Alta – si viene ?scope=... lo preseleccionamos */
    public function create(Request $r)
    {
        $scope      = $r->query('scope');
        $suppliers  = Supplier::orderBy('name')->get();
        $terms      = PaymentTerm::orderBy('name')->get();
        $accounts  = Account::orderBy('code')->get();
        $centers   = CostCenter::orderBy('code')->get();
        $products  = Product::where('is_active', true)->orderBy('name')->get();

        $termsData = $terms->map(function (PaymentTerm $term) {
            return [
                'id'   => $term->id,
                'name' => $term->name,
                'days' => $term->days ?? [],
            ];
        })->values();

        return view('documents.create', [
            'scope'     => $scope,
            'suppliers' => $suppliers,
            'terms'     => $terms,
            'termsData' => $termsData,
            'accounts'  => $accounts,
            'centers'   => $centers,
            'products'  => $products,
        ]);
    }

    public function store(Request $r, DocumentService $svc)
    {
        $supplierRule = 'nullable|exists:suppliers,id';
        if ($r->input('scope') === 'purchase') {
            $supplierRule = 'required|exists:suppliers,id';
        }

        // Allowed doctypes depend on the scope. Payment Orders are created from the Payments module
        $scope = $r->input('scope');
        $allowedDoctypes = $scope === 'sale'
            ? ['invoice', 'credit_note', 'debit_note', 'receipt', 'fund_movement']
            : ['invoice', 'credit_note', 'debit_note', 'receipt'];

        $data = $r->validate([
            'scope'           => 'required|in:purchase,sale',
            'doctype'         => ['required', 'in:' . implode(',', $allowedDoctypes)],
            'number'          => 'required|string|max:30',
            'supplier_id'     => $supplierRule,
            'issue_date'      => 'required|date',
            'payment_term_id' => 'nullable|exists:payment_terms,id',
            'tax'             => 'nullable|numeric',
            'notes'           => 'nullable|string|max:500',
        ]);

        $r->validate([
            'lines' => 'required|array|min:1',
            'lines.*.product_id' => 'nullable|exists:products,id',
            'lines.*.concept' => 'nullable|string|max:255',
            'lines.*.qty' => 'required|numeric|min:0.0001',
            'lines.*.unit' => 'nullable|string|max:25',
            'lines.*.price' => 'required|numeric|min:0',
            'lines.*.account_id' => 'nullable|exists:accounts,id',
            'lines.*.cost_center_id' => 'nullable|exists:cost_centers,id',
        ]);

        $rawLines = collect($r->input('lines', []));
        $productIds = $rawLines->pluck('product_id')->filter()->unique()->all();
        $productsById = $productIds ? Product::whereIn('id', $productIds)->get()->keyBy('id') : collect();

        $lines = $rawLines->map(function ($ln) use ($productsById) {
            $concept = $ln['concept'] ?? null;
            if ((!$concept || trim($concept) === '') && !empty($ln['product_id'])) {
                $concept = optional($productsById->get((int) $ln['product_id']))->name ?? 'Item';
            }

            return [
                'product_id'     => $ln['product_id'] ?? null,
                'concept'        => $concept ?: 'Ítem',
                'qty'            => (float)($ln['qty'] ?? 1),
                'unit'           => $ln['unit'] ?? null,
                'price'          => (float)($ln['price'] ?? 0),
                'account_id'     => $ln['account_id'] ?? null,
                'cost_center_id' => $ln['cost_center_id'] ?? null,
            ];
        })->all();

        foreach ($lines as $line) {
            if (empty($line['concept']) && empty($line['product_id'])) {
                return back()->withErrors('Cada ítem debe tener un concepto o producto seleccionado.')->withInput();
            }
        }

        $doc = $svc->storeDocument([
            'header' => $data,   // incluye supplier_id cuando corresponde
            'lines'  => $lines,
            'tax'    => $r->input('tax', 0),
        ]);

        // If the created document is a credit/debit note (imputable), redirect and open imputations
        $openAlloc = in_array($doc->doctype, ['credit_note', 'debit_note'], true);

        return redirect()->route('documents.show', $doc)->with(['ok' => 'Comprobante creado', 'open_alloc' => $openAlloc]);
    }


    public function show(Document $document)
    {
        $document->load(['supplier','lines.account','lines.costCenter','installments','payments','allocations']);
        return view('documents.show', compact('document'));
    }

    public function spending(Request $r)
    {
        $validated = $r->validate([
            'from' => 'nullable|date',
            'to'   => 'nullable|date',
        ]);

        $now = now();
        $from = $validated['from'] ?? $now->copy()->startOfMonth()->toDateString();
        $to   = $validated['to']   ?? $now->copy()->endOfMonth()->toDateString();

        $fromDate = Carbon::parse($from)->startOfDay();
        $toDate   = Carbon::parse($to)->endOfDay();
        if ($fromDate->gt($toDate)) {
            [$fromDate, $toDate] = [$toDate->copy()->startOfDay(), $fromDate->copy()->endOfDay()];
            $from = $fromDate->toDateString();
            $to   = $toDate->toDateString();
        } else {
            $from = $fromDate->toDateString();
            $to   = $toDate->toDateString();
        }

        $filters = [
            'from' => $from,
            'to'   => $to,
        ];

        $base = Document::query()
            ->where('scope', 'purchase')
            ->whereDate('issue_date', '>=', $filters['from'])
            ->whereDate('issue_date', '<=', $filters['to']);

        $totalsRow = (clone $base)
            ->selectRaw("COUNT(*) as documents_count, COALESCE(SUM(CASE WHEN doctype IN ('invoice','debit_note') THEN total ELSE -total END),0) as total_sum")
            ->first();

        $summary = [
            'documents' => (int) ($totalsRow->documents_count ?? 0),
            'total'     => (float) ($totalsRow->total_sum ?? 0),
        ];

        $rawDaily = (clone $base)
            ->selectRaw("issue_date as day, COUNT(*) as documents_count, COALESCE(SUM(CASE WHEN doctype IN ('invoice','debit_note') THEN total ELSE -total END),0) as total_sum")
            ->groupBy('day')
            ->orderBy('day')
            ->get()
            ->keyBy(function ($row) {
                return Carbon::parse($row->day)->toDateString();
            });

        $cursor = Carbon::parse($filters['from']);
        $end = Carbon::parse($filters['to']);
        $daily = collect();
        $running = 0.0;
        while ($cursor->lte($end)) {
            $key = $cursor->toDateString();
            $row = $rawDaily->get($key);
            $amount = $row ? (float) $row->total_sum : 0.0;
            $docs = $row ? (int) $row->documents_count : 0;
            $running = round($running + $amount, 2);

            $daily->push([
                'date'           => $key,
                'label'          => $cursor->format('d/m/Y'),
                'documents'      => $docs,
                'amount'         => round($amount, 2),
                'running_total'  => $running,
            ]);

            $cursor->addDay();
        }

        $daysCount = max(1, $daily->count());
        $spentDays = max(1, $daily->where('amount', '>', 0)->count());
        $peakDay = $daily->sortByDesc('amount')->first();

        $metrics = [
            'days'           => $daily->count(),
            'avg_per_day'    => $summary['total'] / $daysCount,
            'avg_on_spent'   => $summary['total'] / $spentDays,
            'peak_day'       => $peakDay,
            'running_total'  => $daily->last()['running_total'] ?? 0.0,
        ];

        $chart = [
            'labels'     => $daily->pluck('label')->all(),
            'series'     => $daily->pluck('amount')->all(),
            'cumulative' => $daily->pluck('running_total')->all(),
        ];

        $shortcuts = [
            [
                'label' => 'Mes actual',
                'from'  => $now->copy()->startOfMonth()->toDateString(),
                'to'    => $now->copy()->endOfMonth()->toDateString(),
            ],
            [
                'label' => 'Mes anterior',
                'from'  => $now->copy()->subMonthNoOverflow()->startOfMonth()->toDateString(),
                'to'    => $now->copy()->subMonthNoOverflow()->endOfMonth()->toDateString(),
            ],
            [
                'label' => 'Ultimos 30 dias',
                'from'  => $now->copy()->subDays(29)->toDateString(),
                'to'    => $now->toDateString(),
            ],
        ];

        return view('purchases.spending', [
            'filters' => $filters,
            'summary' => $summary,
            'metrics' => $metrics,
            'daily'   => $daily,
            'chart'   => $chart,
            'shortcuts' => $shortcuts,
        ]);
    }

    private function applyFilters(Builder $builder, ?string $scope, array $filters): Builder
    {
        if (in_array($scope, ['sale', 'purchase'], true)) {
            $builder->where('scope', $scope);
        }

        if (!empty($filters['from'])) {
            $builder->whereDate('issue_date', '>=', $filters['from']);
        }

        if (!empty($filters['to'])) {
            $builder->whereDate('issue_date', '<=', $filters['to']);
        }

        if (!empty($filters['supplier_id'])) {
            $builder->where('supplier_id', $filters['supplier_id']);
        }

        if (!empty($filters['search'])) {
            $term = $filters['search'];
            $builder->where(function ($query) use ($term) {
                $query->where('number', 'like', "%{$term}%")
                    ->orWhere('notes', 'like', "%{$term}%");
            });
        }

        return $builder;
    }
}

