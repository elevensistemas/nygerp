<?php
namespace App\Http\Controllers;

use App\Models\Document;
use Illuminate\Support\Facades\DB;

class GraphController extends Controller
{
    public function index()
    {
        $now = now();

        $topSuppliersRows = Document::query()
            ->select('suppliers.name as label', DB::raw("SUM(CASE WHEN documents.doctype IN ('invoice','debit_note') THEN documents.total ELSE -documents.total END) as total"))
            ->join('suppliers', 'suppliers.id', '=', 'documents.supplier_id')
            ->where('documents.scope', 'purchase')
            ->whereDate('documents.issue_date', '>=', $now->copy()->subDays(30)->toDateString())
            ->groupBy('suppliers.name')
            ->orderByDesc('total')
            ->limit(5)
            ->get();

        $topSuppliers = [
            'labels' => $topSuppliersRows->pluck('label')->values()->all(),
            'totals' => $topSuppliersRows->pluck('total')->map(fn ($val) => round((float) $val, 2))->values()->all(),
        ];

        $months = collect();
        for ($i = 5; $i >= 0; $i--) {
            $months->push($now->copy()->subMonths($i)->startOfMonth());
        }

        $monthlyRaw = Document::query()
            ->selectRaw("DATE_FORMAT(issue_date, '%Y-%m-01') as period")
            ->selectRaw("scope")
            ->selectRaw("SUM(
                CASE
                    WHEN scope = 'purchase' AND doctype IN ('invoice','debit_note') THEN total
                    WHEN scope = 'purchase' THEN -total
                    WHEN scope = 'sale' AND doctype IN ('invoice','debit_note') THEN total
                    WHEN scope = 'sale' AND doctype IN ('credit_note','receipt') THEN -total
                    ELSE total
                END
            ) as total")
            ->whereDate('issue_date', '>=', $months->first()->toDateString())
            ->groupBy('period', 'scope')
            ->orderBy('period')
            ->get();

        $monthlyMap = [];
        foreach ($monthlyRaw as $row) {
            $monthlyMap[$row->scope][$row->period] = (float) $row->total;
        }

        $monthlyLabels = [];
        $monthlyPurchases = [];
        $monthlySales = [];
        foreach ($months as $month) {
            $key = $month->format('Y-m-01');
            $label = mb_convert_case(
                $month->locale(app()->getLocale() ?? 'es')->isoFormat('MMM YYYY'),
                MB_CASE_TITLE,
                'UTF-8'
            );
            $monthlyLabels[] = $label;
            $monthlyPurchases[] = $monthlyMap['purchase'][$key] ?? 0.0;
            $monthlySales[] = $monthlyMap['sale'][$key] ?? 0.0;
        }

        $monthlyComparison = [
            'labels'    => $monthlyLabels,
            'purchases' => $monthlyPurchases,
            'sales'     => $monthlySales,
        ];

        return view('graphs.index', [
            'topSuppliers'      => $topSuppliers,
            'monthlyComparison' => $monthlyComparison,
        ]);
    }
}
