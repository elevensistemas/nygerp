<?php

namespace App\Http\Controllers;

use App\Models\{
    Document,
    PaymentTerm,
    ScheduledInstallment
};
use App\Services\DocumentService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DashboardController extends Controller
{
    private DocumentService $documents;

    public function __construct(DocumentService $documents)
    {
        $this->documents = $documents;
    }

    public function index(Request $request)
    {
        $this->syncMissingInstallments();

        $filters = $this->resolveFilters($request);
        $terms   = PaymentTerm::orderBy('name')->get(['id', 'name']);
        $today   = Carbon::today();

        $upcoming = $this->agendaQuery($filters, 'upcoming')
            ->orderBy('due_date')
            ->orderBy('installment_number')
            ->get();

        $overdue = $this->agendaQuery($filters, 'overdue')
            ->orderBy('due_date')
            ->orderBy('installment_number')
            ->get();

        $this->decorateInstallments($upcoming, $today);
        $this->decorateInstallments($overdue, $today);

        $calendarGroups = $this->buildCalendarGroups($upcoming, $overdue, $today);

        $summary = [
            'upcoming_count' => $upcoming->count(),
            'upcoming_total' => round((float) $upcoming->sum('amount'), 2),
            'overdue_count'  => $overdue->count(),
            'overdue_total'  => round((float) $overdue->sum('amount'), 2),
            'window'         => [
                'from' => $filters['from'],
                'to'   => $filters['to'],
            ],
        ];

        $filterQuery = array_filter([
            'from'    => $filters['from'],
            'to'      => $filters['to'],
            'term_id' => $filters['term_id'],
            'view'    => $filters['view'],
        ], fn ($value) => $value !== null && $value !== '');

        $viewToggleQuery = $filterQuery;
        unset($viewToggleQuery['view']);

        $exportQuery = array_filter([
            'from'    => $filters['from'],
            'to'      => $filters['to'],
            'term_id' => $filters['term_id'],
        ], fn ($value) => $value !== null && $value !== '');

        return view('dashboard', [
            'calendarGroups'  => $calendarGroups,
            'upcoming'        => $upcoming,
            'overdue'         => $overdue,
            'summary'         => $summary,
            'filters'         => $filters,
            'terms'           => $terms,
            'filterQuery'     => $filterQuery,
            'viewToggleQuery' => $viewToggleQuery,
            'exportQuery'     => $exportQuery,
            'viewMode'        => $filters['view'],
        ]);
    }

    public function exportPaymentsCsv(Request $request): StreamedResponse
    {
        $filters = $this->resolveFilters($request);
        $today   = Carbon::today();

        $upcoming = $this->agendaQuery($filters, 'upcoming')
            ->orderBy('due_date')
            ->orderBy('installment_number')
            ->get();

        $overdue = $this->agendaQuery($filters, 'overdue')
            ->orderBy('due_date')
            ->orderBy('installment_number')
            ->get();

        $this->decorateInstallments($upcoming, $today);
        $this->decorateInstallments($overdue, $today);

        $rows = $overdue
            ->merge($upcoming)
            ->sortBy(fn ($item) => sprintf(
                '%s-%04d',
                optional($item->due_date)->format('Ymd') ?? '99999999',
                $item->installment_number ?? 0
            ))
            ->map(function (ScheduledInstallment $i) {
                $supplier = $i->document->supplier;
                return [
                    'Fecha'        => optional($i->due_date)->format('Y-m-d'),
                    'Beneficiario' => $supplier->name ?? '',
                    'CUIT'         => $supplier->tax_id ?? '',
                    'CBU'          => $supplier->bank_cbu ?? '',
                    'Alias'        => $supplier->bank_alias ?? '',
                    'Condición'    => optional($i->document->term)->name ?? '',
                    'Importe'      => number_format((float) $i->amount, 2, '.', ''),
                    'Referencia'   => $i->document->number ?? '',
                    'Estado'       => $i->status_label ?? ($i->due_date && $i->due_date->isPast() ? 'Vencido' : 'Pendiente'),
                ];
            });

        $headers = [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename=pagos.csv',
        ];

        return response()->stream(function () use ($rows) {
            $out = fopen('php://output', 'w');
            if (isset($rows[0])) {
                fputcsv($out, array_keys($rows[0]));
            }
            foreach ($rows as $r) {
                fputcsv($out, $r);
            }
            fclose($out);
        }, 200, $headers);
    }

    private function syncMissingInstallments(): void
    {
        Document::query()
            ->where('scope', 'purchase')
            ->where(function ($q) {
                $q->whereNull('affects_current_account')
                    ->orWhere('affects_current_account', true);
            })
            ->whereDoesntHave('installments')
            ->with(['term'])
            ->chunkById(50, function ($documents) {
                foreach ($documents as $doc) {
                    foreach ($this->documents->planInstallments($doc) as $row) {
                        ScheduledInstallment::create([
                            'document_id'        => $doc->id,
                            'installment_number' => $row['number'],
                            'due_date'           => $row['due_date'],
                            'amount'             => $row['amount'],
                        ]);
                    }
                }
            });
    }

    private function resolveFilters(Request $request): array
    {
        $today = Carbon::today();

        $fromInput = $request->input('from');
        $toInput   = $request->input('to');

        $from = $fromInput ? Carbon::parse($fromInput) : $today->copy();
        $to   = $toInput ? Carbon::parse($toInput) : $from->copy()->addDays(30);

        if ($to->lessThan($from)) {
            [$from, $to] = [$to->copy(), $from->copy()];
        }

        $termId = $request->input('term_id');

        return [
            'from'      => $from->toDateString(),
            'to'        => $to->toDateString(),
            'view'      => $request->input('view', 'calendar'),
            'term_id'   => $termId ?: null,
            'from_date' => $from,
            'to_date'   => $to,
        ];
    }

    private function agendaQuery(array $filters, string $period = 'upcoming')
    {
        $builder = ScheduledInstallment::with(['document.supplier', 'document.term', 'document.allocationsReceived'])
            ->where(function ($q) {
                $q->whereNull('paid')->orWhere('paid', false);
            })
            ->where('amount', '>', 0.01)
            ->whereHas('document', function ($q) {
                // Solo documentos de compra tipo factura/nota de débito deben aparecer en la agenda
                $q->where('scope', 'purchase')
                  ->whereIn('doctype', ['invoice', 'debit_note']);
            });

        if (!empty($filters['term_id'])) {
            $builder->whereHas('document', function ($q) use ($filters) {
                $q->where('payment_term_id', $filters['term_id']);
            });
        }

        if ($period === 'overdue') {
            $builder->whereDate('due_date', '<', Carbon::today()->toDateString());
        } else {
            $builder->whereDate('due_date', '>=', $filters['from'])
                ->whereDate('due_date', '<=', $filters['to']);
        }

        return $builder;
    }

    private function decorateInstallments(Collection $items, Carbon $today): void
    {
        $items->each(function (ScheduledInstallment $installment) use ($today) {
            $diff = $today->diffInDays($installment->due_date, false);

            if ($diff < 0) {
                $installment->status_key   = 'overdue';
                $installment->status_label = 'Vencido';
                $installment->status_order = 0;
            } elseif ($diff <= 1) {
                $installment->status_key   = 'imminent';
                $installment->status_label = 'Hoy / Mañana';
                $installment->status_order = 1;
            } elseif ($diff <= 3) {
                $installment->status_key   = 'soon';
                $installment->status_label = 'Próximos 3 días';
                $installment->status_order = 2;
            } else {
                $installment->status_key   = 'future';
                $installment->status_label = 'Más adelante';
                $installment->status_order = 3;
            }

            $installment->days_diff = $diff;
        });
    }

    private function buildCalendarGroups(Collection $upcoming, Collection $overdue, Carbon $today): Collection
    {
        $groups   = collect();
        $todayKey = $today->toDateString();

        if ($overdue->isNotEmpty()) {
            $groups[$todayKey] = collect($overdue->all());
        }

        foreach ($upcoming as $item) {
            $key = $item->due_date->toDateString();
            if (!$groups->has($key)) {
                $groups[$key] = collect();
            }
            $groups[$key]->push($item);
        }

        return $groups
            ->map(fn (Collection $group) => $group
                ->sortBy(fn ($item) => sprintf(
                    '%02d-%s-%04d',
                    $item->status_order ?? 5,
                    optional($item->due_date)->format('Ymd') ?? '99999999',
                    $item->installment_number ?? 0
                ))
                ->values())
            ->sortKeys();
    }
}
