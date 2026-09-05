<?php
// app/Services/DocumentService.php

namespace App\Services;

use App\Models\{
    Account,
    Document,
    DocumentLine,
    LedgerEntry,
    Product,
    ScheduledInstallment,
    Supplier,
    Tax
};
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DocumentService
{
    /**
     * Crea un comprobante con líneas, cuotas y asientos contables simples.
     *
     * @param  array{header:array, lines?:array<int,array>, tax?:float} $data
     */
    public function storeDocument(array $data): Document
    {
        return DB::transaction(function () use ($data) {
            /** @var Document $doc */
            $doc = Document::create($data['header']);

            // Detalle e importes
            $lines = collect($data['lines'] ?? []);
            // Aseguramos que cada línea sea array (por si vienen objetos/stdClass)
            $lines = $lines->map(static fn($ln) => is_array($ln) ? $ln : (array) $ln);

            $productIds = $lines->pluck('product_id')->filter()->unique()->all();
            $productMap = $productIds ? Product::whereIn('id', $productIds)->get()->keyBy('id') : collect();

            $manualTax = round((float) ($data['tax'] ?? 0), 2);
            $subtotal = 0.0;
            $taxLinesTotal = 0.0;

            foreach ($lines as $ln) {
                $isTaxLine = !empty($ln['is_tax']);

                // Acceso seguro al product_id y al Product
                $productId = $ln['product_id'] ?? null;
                $product = $productId ? $productMap->get((int) $productId) : null;

                $qty = (float) ($ln['qty'] ?? 1);
                // Evitar acceso a propiedad en null
                $unit = $ln['unit'] ?? ($product ? $product->unit : null);

                $price = (float) ($ln['price'] ?? 0);
                if ($price <= 0 && $product) {
                    $price = (float) $product->default_price;
                }

                // Evitar acceso a propiedad en null
                $accountId = $ln['account_id'] ?? ($product ? $product->default_account_id : null);
                $costCenterId = $ln['cost_center_id'] ?? ($product ? $product->default_cost_center_id : null);

                $concept = $ln['concept'] ?? null;
                if (!$concept && $product) {
                    $concept = $product->name;
                }

                $payload = [
                    'document_id'     => $doc->id,
                    'product_id'      => $product ? $product->id : null,
                    'concept'         => $concept ?: 'Item',
                    'qty'             => $qty,
                    'unit'            => $unit,
                    'price'           => $price,
                    'account_id'      => $accountId,
                    'cost_center_id'  => $costCenterId,
                ];
                $payload['line_total'] = round($payload['qty'] * $payload['price'], 2);

                DocumentLine::create($payload);
                if ($isTaxLine) {
                    $taxLinesTotal += $payload['line_total'];
                } else {
                    $subtotal += $payload['line_total'];
                }
            }

            $supplier = null;
            $scope = $data['header']['scope'] ?? null;
            $doctypeHeader = strtolower((string) ($data['header']['doctype'] ?? ''));
            $supplierId = $data['header']['supplier_id'] ?? null;
            if ($scope === 'purchase' && $doctypeHeader === 'invoice' && $supplierId) {
                $supplier = Supplier::with(['taxes' => function ($query) {
                    $query->where('active', true);
                }])->find($supplierId);
            }

            if ($supplier) {
                $autoTaxes = $this->calculateInvoiceTaxes($supplier, $subtotal, $manualTax + $taxLinesTotal);
                foreach ($autoTaxes['lines'] as $taxLine) {
                    DocumentLine::create([
                        'document_id'    => $doc->id,
                        'product_id'     => null,
                        'concept'        => $taxLine['concept'],
                        'qty'            => 1,
                        'unit'           => null,
                        'price'          => $taxLine['amount'],
                        'line_total'     => $taxLine['amount'],
                        'account_id'     => $taxLine['account_id'],
                        'cost_center_id' => null,
                    ]);
                    $taxLinesTotal += $taxLine['amount'];
                }
            }

            $doc->subtotal = round($subtotal, 2);
            $doc->tax      = round($manualTax + $taxLinesTotal, 2);
            $doc->total    = round($doc->subtotal + $doc->tax, 2);
            $doc->save();

            // Cuotas programadas
            foreach ($this->planInstallments($doc) as $row) {
                ScheduledInstallment::create([
                    'document_id'        => $doc->id,
                    'installment_number' => $row['number'],
                    'due_date'           => $row['due_date'],
                    'amount'             => $row['amount'],
                ]);
            }

            $this->persistLedgerEntries($doc);

            return $doc->load(['lines', 'installments']);
        });
    }

    /**
     * Calcula cuotas previstas (sin persistir) para documentos de compra.
     *
     * @return array<int,array{number:int,due_date:\Carbon\CarbonInterface,amount:float}>
     */
    public function planInstallments(Document $doc): array
    {
        if (!($doc->affects_current_account ?? true)) {
            return [];
        }

        if ($doc->scope !== 'purchase') {
            return [];
        }

        $doctype = strtolower((string) $doc->doctype);
        if (!in_array($doctype, ['invoice', 'debit_note'], true)) {
            return [];
        }

        if ($doc->total <= 0) {
            return [];
        }

        $doc->loadMissing('term');

        $days = $this->normalizeDays($doc->term->days ?? null);
        if (empty($days)) {
            $days = [0];
        }

        $days = array_values(array_unique(array_map('intval', $days)));
        sort($days);

        $count = max(1, count($days));
        $base  = $count > 1 ? floor(($doc->total / $count) * 100) / 100 : round($doc->total, 2);
        $rest  = round($doc->total - ($base * ($count - 1)), 2);

        $issue = Carbon::parse($doc->issue_date);
        $plan  = [];

        foreach ($days as $index => $day) {
            $amount = $index < $count - 1 ? $base : $rest;
            $plan[] = [
                'number'   => $index + 1,
                'due_date' => $issue->copy()->addDays((int) $day),
                'amount'   => round($amount, 2),
            ];
        }

        return $plan;
    }

    /**
     * Normaliza el campo days (array/json/string/int) a enteros >= 0.
     *
     * @param  mixed $daysRaw
     * @return int[]
     */
    private function normalizeDays($daysRaw): array
    {
        if (is_array($daysRaw)) {
            return array_values(array_filter(
                array_map('intval', $daysRaw),
                static fn ($d) => $d >= 0
            ));
        }

        if (is_string($daysRaw)) {
            if (strpos($daysRaw, ',') !== false) {
                return array_values(array_filter(
                    array_map(
                        'intval',
                        array_map('trim', explode(',', $daysRaw))
                    ),
                    static fn ($d) => $d >= 0
                ));
            }

            if (is_numeric($daysRaw)) {
                $val = (int) $daysRaw;
                return $val >= 0 ? [$val] : [];
            }
        }

        if (is_numeric($daysRaw)) {
            $val = (int) $daysRaw;
            return $val >= 0 ? [$val] : [];
        }

        return [];
    }

    public function persistLedgerEntries(Document $doc): void
    {
        if (!($doc->affects_ledger ?? true)) {
            return;
        }

        $profile = $this->ledgerProfile($doc);
        if ($profile === null) {
            return;
        }

        $doc->loadMissing('lines');
        $lines = $doc->lines->filter(fn ($line) => $line->account_id);
        if ($lines->isEmpty()) {
            return;
        }

        $amounts = $this->allocateLineAmounts($doc, $lines);

        foreach ($lines as $line) {
            $amount = $amounts[$line->id] ?? 0.0;
            if ($amount <= 0) {
                continue;
            }

            LedgerEntry::create([
                'document_id'    => $doc->id,
                'entry_date'     => $doc->issue_date,
                'account_id'     => $line->account_id,
                'cost_center_id' => $line->cost_center_id,
                'debit'          => $profile['lines'] === 'debit' ? $amount : 0,
                'credit'         => $profile['lines'] === 'credit' ? $amount : 0,
                'description'    => $line->concept,
                'generated_by'   => 'document',
            ]);
        }

        $contraAmount = round((float) $doc->total, 2);
        if ($contraAmount === 0.0) {
            return;
        }

        LedgerEntry::create([
            'document_id' => $doc->id,
            'entry_date'  => $doc->issue_date,
            'account_id'  => $this->contraAccountId($doc->scope),
            'debit'       => $profile['contra'] === 'debit' ? $contraAmount : 0,
            'credit'      => $profile['contra'] === 'credit' ? $contraAmount : 0,
            'description' => strtoupper((string) $doc->doctype) . ' ' . $doc->number,
            'generated_by'=> 'document',
        ]);
    }

    private function ledgerProfile(Document $doc): ?array
    {
        $scope = strtolower((string) $doc->scope);
        $doctype = strtolower((string) $doc->doctype);

        $map = [
            'purchase' => [
                'invoice'       => ['lines' => 'debit',  'contra' => 'credit'],
                'debit_note'    => ['lines' => 'debit',  'contra' => 'credit'],
                'credit_note'   => ['lines' => 'credit', 'contra' => 'debit'],
                'payment_order' => ['lines' => 'credit', 'contra' => 'debit'],
                'receipt'       => ['lines' => 'credit', 'contra' => 'debit'],
            ],
            'sale' => [
                'invoice'       => ['lines' => 'credit', 'contra' => 'debit'],
                'debit_note'    => ['lines' => 'credit', 'contra' => 'debit'],
                'credit_note'   => ['lines' => 'debit',  'contra' => 'credit'],
                'receipt'       => ['lines' => 'debit',  'contra' => 'credit'],
                'payment_order' => ['lines' => 'debit',  'contra' => 'credit'],
            ],
        ];

        if (!isset($map[$scope][$doctype])) {
            return null;
        }

        return $map[$scope][$doctype];
    }

    private function allocateLineAmounts(Document $doc, Collection $lines): array
    {
        $target = round((float) $doc->total, 2);
        $count = $lines->count();
        $base = $lines->sum(fn ($line) => (float) $line->line_total);

        if ($target === 0.0 || $count === 0) {
            return array_fill_keys($lines->pluck('id')->all(), 0.0);
        }

        $amounts = [];
        $accum = 0.0;

        foreach ($lines->values() as $index => $line) {
            if ($base > 0) {
                $amount = round($target * ((float) $line->line_total) / $base, 2);
            } else {
                $amount = round($target / $count, 2);
            }

            if ($index === $count - 1) {
                $amount = round($target - $accum, 2);
            }

            $amounts[$line->id] = $amount;
            $accum += $amount;
        }

        return $amounts;
    }

    private function contraAccountId(string $scope): int
    {
        $supplierCode = '2.1.01';
        $customerCode = '1.1.03';

        if (function_exists('account_id')) {
            return $scope === 'purchase'
                ? (int) account_id($supplierCode)
                : (int) account_id($customerCode);
        }

        $code = $scope === 'purchase' ? $supplierCode : $customerCode;
        $account = Account::where('code', $code)->first();
        if ($account) {
            return (int) $account->id;
        }

        $fallbackType = $scope === 'purchase' ? 'liability' : 'asset';
        $account = Account::where('type', $fallbackType)->orderBy('id')->firstOrFail();

        return (int) $account->id;
    }

    private function calculateInvoiceTaxes(?Supplier $supplier, float $subtotal, float $manualTax): array
    {
        if (!$supplier) {
            return [
                'total' => 0.0,
                'lines' => [],
            ];
        }

        $taxes = $supplier->taxes
            ->filter(function (Tax $tax) {
                return $tax->active && $tax->applies_on === 'invoice';
            })
            ->values();

        if ($taxes->isEmpty()) {
            return [
                'total' => 0.0,
                'lines' => [],
            ];
        }

        $total = 0.0;
        $lines = [];

        foreach ($taxes as $tax) {
            if (!$tax->account_id) {
                throw new RuntimeException('El impuesto ' . $tax->code . ' (' . $tax->name . ') necesita una cuenta contable configurada.');
            }

            $rate = (float) $tax->rate;
            if ($rate <= 0.0) {
                continue;
            }

            $base = $tax->base === 'gross'
                ? $subtotal + $manualTax + $total
                : $subtotal;

            if ($base <= 0.0) {
                continue;
            }

            $amount = round(($base * $rate) / 100, 2);
            if ($amount <= 0.0) {
                continue;
            }

            $label = ($tax->type === 'retention' ? 'Retencion ' : 'Percepcion ') . $tax->name;
            $lines[] = [
                'concept'    => $label,
                'amount'     => $amount,
                'account_id' => (int) $tax->account_id,
            ];

            $total = round($total + $amount, 2);
        }

        return [
            'total' => $total,
            'lines' => $lines,
        ];
    }
}
