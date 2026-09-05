<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use App\Models\LedgerEntry;
use App\Models\Account;
use App\Models\CostCenter;
use App\Models\Document;
use App\Services\DocumentService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;

class LedgerController extends Controller
{
    public function index(Request $r)
    {
        $from = $r->input('from', now()->startOfMonth()->toDateString());
        $to   = $r->input('to',   now()->endOfMonth()->toDateString());

        $entries = LedgerEntry::with(['account','costCenter','document'])
            ->whereBetween('entry_date', [$from, $to])
            ->orderBy('entry_date')->paginate(30);

        return view('ledger.index', compact('entries','from','to'));
    }

    public function create()
    {
        return view('ledger.create', [
            'accounts'=>Account::orderBy('code')->get(),
            'centers'=>CostCenter::orderBy('code')->get(),
            'today'=>now()->toDateString()
        ]);
    }

    public function store(Request $r)
    {
        $data = $r->validate([
            'entry_date'=>'required|date',
            'rows'=>'required|array|min:1',
            'rows.*.account_id'=>'required|exists:accounts,id',
            'rows.*.cost_center_id'=>'nullable|exists:cost_centers,id',
            'rows.*.debit'=>'nullable|numeric',
            'rows.*.credit'=>'nullable|numeric',
            'rows.*.description'=>'nullable|string'
        ]);

        // validación simple: suma débitos == suma créditos
        $sumD = collect($data['rows'])->sum(fn($x)=> (float)($x['debit'] ?? 0));
        $sumC = collect($data['rows'])->sum(fn($x)=> (float)($x['credit'] ?? 0));
        if (round($sumD,2) !== round($sumC,2)) {
            return back()->withErrors('Los débitos y créditos deben ser iguales.')->withInput();
        }

        foreach ($data['rows'] as $row) {
            LedgerEntry::create([
                'entry_date'=>$data['entry_date'],
                'account_id'=>$row['account_id'],
                'cost_center_id'=>$row['cost_center_id'] ?? null,
                'debit'=>$row['debit'] ?? 0,
                'credit'=>$row['credit'] ?? 0,
                'description'=>$row['description'] ?? null,
                'generated_by'=>'manual',
            ]);
        }
        return redirect()->route('ledger.index')->with('ok','Asiento registrado');
    }

    public function rebuild(Request $request, DocumentService $documents)
    {
        $data = $request->validate([
            'from'  => 'required|date',
            'to'    => 'required|date|after_or_equal:from',
            'scope' => 'nullable|in:purchase,sale,both',
        ]);

        $scope = $data['scope'] ?? 'both';

        $query = Document::query()
            ->where('affects_ledger', true)
            ->whereBetween('issue_date', [$data['from'], $data['to']]);

        if ($scope !== 'both') {
            $query->where('scope', $scope);
        }

        $documentsToProcess = $query->orderBy('issue_date')->get();

        if ($documentsToProcess->isEmpty()) {
            return back()->with('ok', 'No se encontraron comprobantes para regenerar asientos.');
        }

        $docIds = $documentsToProcess->pluck('id');

        LedgerEntry::whereIn('document_id', $docIds)
            ->where('generated_by', 'document')
            ->delete();

        foreach ($documentsToProcess as $doc) {
            $documents->persistLedgerEntries($doc);
        }

        return back()->with('ok', 'Asientos regenerados para ' . $documentsToProcess->count() . ' comprobantes.');
    }

    public function lists(Request $r)
    {
        $validated = $r->validate([
            'from'           => 'nullable|date',
            'to'             => 'nullable|date',
            'group_by'       => 'nullable|in:month,quarter,year',
            'account_id'     => 'nullable|exists:accounts,id',
            'cost_center_id' => 'nullable|exists:cost_centers,id',
        ]);

        $groupBy = $validated['group_by'] ?? 'month';
        $groupBy = in_array($groupBy, ['month', 'quarter', 'year'], true) ? $groupBy : 'month';

        $now = now();
        $from = $validated['from'] ?? $now->copy()->startOfYear()->toDateString();
        $to   = $validated['to']   ?? $now->copy()->endOfYear()->toDateString();

        if (Carbon::parse($from)->gt(Carbon::parse($to))) {
            [$from, $to] = [$to, $from];
        }

        $filters = [
            'from'           => $from,
            'to'             => $to,
            'group_by'       => $groupBy,
            'account_id'     => $validated['account_id'] ?? null,
            'cost_center_id' => $validated['cost_center_id'] ?? null,
        ];

        $base = LedgerEntry::query()
            ->whereDate('entry_date', '>=', $filters['from'])
            ->whereDate('entry_date', '<=', $filters['to'])
            ->when($filters['account_id'], fn ($q, $accountId) => $q->where('account_id', $accountId))
            ->when($filters['cost_center_id'], fn ($q, $centerId) => $q->where('cost_center_id', $centerId));

        $totalsRow = (clone $base)
            ->selectRaw('COUNT(*) as entries_count, COALESCE(SUM(debit),0) as debit_sum, COALESCE(SUM(credit),0) as credit_sum')
            ->first();

        $summary = [
            'entries' => (int) ($totalsRow->entries_count ?? 0),
            'debit'   => (float) ($totalsRow->debit_sum ?? 0),
            'credit'  => (float) ($totalsRow->credit_sum ?? 0),
        ];
        $summary['balance'] = $summary['debit'] - $summary['credit'];

        $groupQuery = (clone $base);
        switch ($groupBy) {
            case 'year':
                $groupQuery->selectRaw(
                    'YEAR(entry_date) as year, ' .
                    'COUNT(*) as entries_count, ' .
                    'COALESCE(SUM(debit),0) as debit_sum, ' .
                    'COALESCE(SUM(credit),0) as credit_sum'
                )
                ->groupByRaw('YEAR(entry_date)')
                ->orderBy('year', 'desc');
                break;
            case 'quarter':
                $groupQuery->selectRaw(
                    'YEAR(entry_date) as year, ' .
                    'QUARTER(entry_date) as quarter, ' .
                    'COUNT(*) as entries_count, ' .
                    'COALESCE(SUM(debit),0) as debit_sum, ' .
                    'COALESCE(SUM(credit),0) as credit_sum'
                )
                ->groupByRaw('YEAR(entry_date), QUARTER(entry_date)')
                ->orderBy('year', 'desc')
                ->orderBy('quarter', 'desc');
                break;
            default:
                $groupQuery->selectRaw(
                    'YEAR(entry_date) as year, ' .
                    'MONTH(entry_date) as month, ' .
                    'COUNT(*) as entries_count, ' .
                    'COALESCE(SUM(debit),0) as debit_sum, ' .
                    'COALESCE(SUM(credit),0) as credit_sum'
                )
                ->groupByRaw('YEAR(entry_date), MONTH(entry_date)')
                ->orderBy('year', 'desc')
                ->orderBy('month', 'desc');
                break;
        }

        $periods = $groupQuery->get()->map(function ($row) use ($groupBy) {
            $entries = (int) ($row->entries_count ?? 0);
            $debit = (float) ($row->debit_sum ?? 0);
            $credit = (float) ($row->credit_sum ?? 0);
            $balance = $debit - $credit;

            switch ($groupBy) {
                case 'year':
                    $start = Carbon::create((int) $row->year, 1, 1)->startOfDay();
                    $end = $start->copy()->endOfYear();
                    $label = (string) $row->year;
                    break;
                case 'quarter':
                    $quarter = max(1, min(4, (int) ($row->quarter ?? 1)));
                    $startMonth = (($quarter - 1) * 3) + 1;
                    $start = Carbon::create((int) $row->year, $startMonth, 1)->startOfDay();
                    $end = $start->copy()->endOfQuarter();
                    $label = 'T' . $quarter . ' ' . $row->year;
                    break;
                default:
                    $month = max(1, min(12, (int) ($row->month ?? 1)));
                    $start = Carbon::create((int) $row->year, $month, 1)->startOfDay();
                    $end = $start->copy()->endOfMonth();
                    $label = mb_convert_case(
                        $start->copy()->locale(app()->getLocale() ?? 'es')->isoFormat('MMMM YYYY'),
                        MB_CASE_TITLE,
                        'UTF-8'
                    );
                    break;
            }

            return [
                'label'       => $label,
                'from'        => $start->toDateString(),
                'to'          => $end->toDateString(),
                'from_label'  => $start->format('d/m/Y'),
                'to_label'    => $end->format('d/m/Y'),
                'entries'     => $entries,
                'debit'       => $debit,
                'credit'      => $credit,
                'balance'     => $balance,
                'average'     => $entries > 0 ? $balance / $entries : 0.0,
            ];
        })->values();

        if ($r->query('export') === 'csv') {
            $fileName = 'ledger-periods-' . now()->format('Ymd_His') . '.csv';
            return response()->streamDownload(function () use ($periods) {
                $handle = fopen('php://output', 'w');
                fputcsv($handle, ['Periodo', 'Desde', 'Hasta', 'Movimientos', 'Debe', 'Haber', 'Saldo'], ';');
                foreach ($periods as $period) {
                    fputcsv($handle, [
                        $period['label'],
                        $period['from'],
                        $period['to'],
                        $period['entries'],
                        number_format($period['debit'], 2, '.', ''),
                        number_format($period['credit'], 2, '.', ''),
                        number_format($period['balance'], 2, '.', ''),
                    ], ';');
                }
                fclose($handle);
            }, $fileName, ['Content-Type' => 'text/csv']);
        }

        $availableYears = LedgerEntry::selectRaw('DISTINCT YEAR(entry_date) as year')
            ->orderBy('year', 'desc')
            ->pluck('year')
            ->map(fn ($year) => (int) $year)
            ->filter();

        return view('ledger.lists', [
            'filters'        => $filters,
            'summary'        => $summary,
            'periods'        => $periods,
            'accounts'       => Account::orderBy('code')->get(['id', 'code', 'name']),
            'centers'        => CostCenter::orderBy('code')->get(['id', 'code', 'name']),
            'availableYears' => $availableYears,
        ]);
    }

    public function analytics(Request $request)
    {
        $filters = $this->validateAnalyticsFilters($request);
        $data = $this->buildAnalyticsData($filters);

        return view('ledger.analytics', array_merge($data, [
            'accounts'           => Account::orderBy('code')->get(['id', 'code', 'name']),
            'selectedAccountIds' => $filters['account_ids'],
            'filtersQuery'       => Arr::query([
                'from'     => $filters['from'],
                'to'       => $filters['to'],
                'accounts' => $filters['account_ids'],
            ]),
        ]));
    }

    public function analyticsPdf(Request $request)
    {
        $filters = $this->validateAnalyticsFilters($request);
        $data = $this->buildAnalyticsData($filters);

        $pdf = Pdf::loadView('ledger.analytics_pdf', array_merge($data, [
            'generatedAt' => now(),
        ]))->setPaper('a4', 'portrait');

        $fileName = 'mayor-analitico-' . now()->format('Ymd_His') . '.pdf';
        return $pdf->stream($fileName);
    }

    protected function validateAnalyticsFilters(Request $request): array
    {
        $validated = $request->validate([
            'from' => 'nullable|date',
            'to' => 'nullable|date',
            'accounts' => 'nullable|array',
            'accounts.*' => 'integer|exists:accounts,id',
        ]);

        $from = $validated['from'] ?? now()->copy()->startOfMonth()->toDateString();
        $to = $validated['to'] ?? now()->copy()->endOfMonth()->toDateString();

        if (Carbon::parse($from)->gt(Carbon::parse($to))) {
            [$from, $to] = [$to, $from];
        }

        $accountIds = collect($validated['accounts'] ?? [])
            ->filter()
            ->unique()
            ->values()
            ->map(fn ($id) => (int) $id)
            ->all();

        return [
            'from' => $from,
            'to' => $to,
            'account_ids' => $accountIds,
        ];
    }

    protected function buildAnalyticsData(array $filters): array
    {
        $from = $filters['from'];
        $to = $filters['to'];
        $accountIds = collect($filters['account_ids']);

        $entriesQuery = LedgerEntry::with('account')
            ->whereBetween('entry_date', [$from, $to])
            ->orderBy('account_id')
            ->orderBy('entry_date')
            ->orderBy('id');

        if ($accountIds->isNotEmpty()) {
            $entriesQuery->whereIn('account_id', $accountIds);
        }

        $entries = $entriesQuery->get();

        $openingQuery = LedgerEntry::selectRaw('account_id, COALESCE(SUM(debit - credit), 0) as balance')
            ->where('entry_date', '<', $from);

        if ($accountIds->isNotEmpty()) {
            $openingQuery->whereIn('account_id', $accountIds);
        }

        $openingBalances = $openingQuery
            ->groupBy('account_id')
            ->pluck('balance', 'account_id')
            ->map(fn ($value) => (float) $value);

        $groups = $entries->groupBy('account_id')->map(function ($items) use ($openingBalances) {
            $account = optional($items->first())->account;
            $opening = (float) ($openingBalances[$items->first()->account_id] ?? 0.0);
            $running = $opening;
            $rows = [];
            $debitTotal = 0.0;
            $creditTotal = 0.0;

            foreach ($items as $entry) {
                $debit = (float) ($entry->debit ?? 0);
                $credit = (float) ($entry->credit ?? 0);
                $running += ($debit - $credit);
                $rows[] = [
                    'date' => Carbon::parse($entry->entry_date)->format('d/m/Y'),
                    'description' => $entry->description,
                    'debit' => $debit,
                    'credit' => $credit,
                    'balance' => $running,
                ];
                $debitTotal += $debit;
                $creditTotal += $credit;
            }

            return [
                'account' => $account,
                'opening' => $opening,
                'rows' => $rows,
                'debit_total' => $debitTotal,
                'credit_total' => $creditTotal,
                'closing' => $running,
                'entries_count' => count($rows),
            ];
        })->values();

        if ($accountIds->isNotEmpty()) {
            $existingIds = $groups->map(function ($group) {
                return optional($group['account'])->id;
            })->filter()->values();

            $missingIds = $accountIds->diff($existingIds);
            if ($missingIds->isNotEmpty()) {
                $missingAccounts = Account::whereIn('id', $missingIds)->orderBy('code')->get();
                foreach ($missingAccounts as $account) {
                    $opening = (float) ($openingBalances[$account->id] ?? 0.0);
                    $groups->push([
                        'account' => $account,
                        'opening' => $opening,
                        'rows' => [],
                        'debit_total' => 0.0,
                        'credit_total' => 0.0,
                        'closing' => $opening,
                        'entries_count' => 0,
                    ]);
                }
            }
        }

        $groups = $groups
            ->sortBy(fn ($group) => optional($group['account'])->code)
            ->values();

        $summary = [
            'opening' => $groups->sum(fn ($group) => $group['opening']),
            'debit' => $groups->sum('debit_total'),
            'credit' => $groups->sum('credit_total'),
            'closing' => $groups->sum(fn ($group) => $group['closing']),
        ];

        return [
            'from' => $from,
            'to' => $to,
            'accountIds' => $accountIds->all(),
            'groups' => $groups,
            'summary' => $summary,
        ];
    }
}