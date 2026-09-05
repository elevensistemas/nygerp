<?php

namespace App\Http\Controllers;

use App\Models\{
    Allocation,
    Document,
    PaymentMethod,
    ScheduledInstallment,
    Supplier,
    Tax
};
use App\Services\DocumentService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PaymentOrderController extends Controller
{
    private DocumentService $documents;

    public function __construct(DocumentService $documents)
    {
        $this->documents = $documents;
    }

    public function create()
    {
        $suppliers = Supplier::orderBy('name')->get(['id', 'name', 'tax_id']);
        $paymentMethods = PaymentMethod::where('active', true)
            ->with('account:id,code,name')
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'description', 'account_id']);

        return view('payments.create', [
            'suppliers' => $suppliers,
            'paymentMethods' => $paymentMethods,
            'nextNumber' => $this->previewNextPaymentOrderNumber(),
            'today'     => Carbon::today()->toDateString(),
        ]);
    }

    public function pending(Request $request): JsonResponse
    {
        $supplierId = $request->query('supplier_id');
        if (!$supplierId) {
            return response()->json([]);
        }

        // Ensure legacy documentos without cuotas generate a default installment so they can be paid
        $documentsWithoutInstallments = Document::query()
            ->where('scope', 'purchase')
            ->where('supplier_id', $supplierId)
            ->whereDoesntHave('installments')
            ->get();

        foreach ($documentsWithoutInstallments as $legacyDocument) {
            $alreadyAllocated = Allocation::where('target_document_id', $legacyDocument->id)->sum('amount');
            $remaining = round((float) $legacyDocument->total - (float) $alreadyAllocated, 2);
            if ($remaining <= 0.01) {
                continue;
            }

            ScheduledInstallment::create([
                'document_id'        => $legacyDocument->id,
                'installment_number' => 1,
                'due_date'           => Carbon::parse($legacyDocument->issue_date ?? now())->toDateString(),
                'amount'             => $remaining,
                'paid'               => false,
            ]);
        }

        $installments = ScheduledInstallment::with(['document.term', 'document.allocationsReceived'])
            ->whereHas('document', function ($q) use ($supplierId) {
                // Solo documentos de compra tipo factura/nota de débito
                $q->where('scope', 'purchase')
                  ->where('supplier_id', $supplierId)
                  ->whereIn('doctype', ['invoice', 'debit_note']);
            })
            ->where(function ($q) {
                $q->whereNull('paid')->orWhere('paid', false);
            })
            ->where('amount', '>', 0.01)
            ->orderBy('due_date')
            ->orderBy('installment_number')
            ->get();

        $rows = $installments->map(function (ScheduledInstallment $item) {
            $doc = $item->document;

            // El importe mostrado es el monto restante guardado en la cuota (el sistema actual actualiza la cuota al imputar)
            $amountDue = round((float) $item->amount, 2);

            return [
                'installment_id'  => $item->id,
                'document_id'     => $doc->id,
                'document_number' => $doc->number,
                'doctype_label'   => $doc->doctype_label,
                'due_date'        => optional($item->due_date)->format('Y-m-d'),
                'amount_due'      => $amountDue,
                'term'            => optional($doc->term)->name,
                'status'          => optional($item->due_date)->isPast() ? 'overdue' : 'pending',
            ];
        })->filter(function ($row) {
            // Filtrar filas con monto 0 o muy cercanos a cero
            return ($row['amount_due'] ?? 0) > 0.01;
        })->values();

        return response()->json($rows);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'issue_date'  => 'required|date',
            'supplier_id' => 'required|exists:suppliers,id',
            'notes'       => 'nullable|string|max:500',
            'lines'                   => 'required|array|min:1',
            'lines.*.concept'         => 'nullable|string|max:255',
            'lines.*.payment_method_id' => 'required|exists:payment_methods,id',
            'lines.*.amount'          => 'required|numeric|min:0.01',
            'allocations'             => 'nullable|array',
            'allocations.*.amount'    => 'required|numeric|min:0.01',
            'allocations.*.document_id'    => 'required|exists:documents,id',
            'allocations.*.installment_id' => 'required|exists:scheduled_installments,id',
        ]);

        $header = [
            'issue_date' => $validated['issue_date'],
            'supplier_id' => $validated['supplier_id'],
            'notes' => $validated['notes'] ?? null,
        ];

        $lineInputs = collect($validated['lines']);
        $methodIds = $lineInputs->pluck('payment_method_id')->unique()->filter()->all();
        $methods = PaymentMethod::whereIn('id', $methodIds)->get()->keyBy('id');

        $lines = $lineInputs->map(function (array $line) use ($methods) {
            $methodId = (int) ($line['payment_method_id'] ?? 0);
            $method = $methods->get($methodId);
            if (!$method) {
                throw new RuntimeException('Medio de pago invalido.');
            }
            $concept = trim($line['concept'] ?? '') ?: ('Pago ' . $method->code);
            $amount = round((float) ($line['amount'] ?? 0), 2);

            return [
                'concept' => $concept,
                'account_id' => (int) $method->account_id,
                'payment_method_id' => $methodId,
                'payment_method_label' => $method->code . ' - ' . $method->name,
                'amount' => $amount,
            ];
        })->values();

        $allocations = collect($validated['allocations'] ?? [])
            ->filter(fn ($row) => isset($row['amount']) && (float) $row['amount'] > 0)
            ->map(function (array $row) {
                return [
                    'installment_id' => (int) $row['installment_id'],
                    'document_id'    => (int) $row['document_id'],
                    'amount'         => round((float) $row['amount'], 2),
                ];
            })
            ->values();

        if ($lines->isEmpty()) {
            return back()->withErrors(['lines' => 'Agrega al menos una linea de pago.'])->withInput();
        }

        $allocationTotal = round($allocations->sum('amount'), 2);
        $lineTotal = round($lines->sum('amount'), 2);

        $supplier = Supplier::with(['taxes' => function ($query) {
            $query->where('active', true);
        }])->findOrFail($header['supplier_id']);

        $retentionLines = $this->buildRetentionLines($supplier, $allocationTotal > 0 ? $allocationTotal : $lineTotal);

        try {
            $lines = $this->applyRetentionsToLines($lines, $retentionLines);
        } catch (RuntimeException $e) {
            return back()->withErrors(['lines' => $e->getMessage()])->withInput();
        }

        if ($retentionLines->isNotEmpty()) {
            $lines = $lines->merge($retentionLines)->values();
        }

        $lineTotal = round($lines->sum('amount'), 2);

        if ($allocationTotal > 0 && abs($lineTotal - $allocationTotal) > 0.01) {
            return back()
                ->withErrors(['allocations' => 'El total seleccionado en facturas ($' . number_format($allocationTotal, 2, ',', '.') . ') no coincide con el total de la orden ($' . number_format($lineTotal, 2, ',', '.') . ').'])
                ->withInput();
        }

        $document = DB::transaction(function () use ($lines, $allocations, $header) {
            $headerWithNumber = array_merge($header, [
                'number' => $this->nextPaymentOrderNumberForUpdate(),
                'scope'      => 'purchase',
                'doctype'    => 'payment_order',
                'tax'        => 0,
            ]);

            $payload = [
                'header' => $headerWithNumber,
                'lines'  => $lines->map(function ($line) {
                    return [
                        'concept'        => $line['concept'],
                        'qty'            => 1,
                        'price'          => $line['amount'],
                        'account_id'     => $line['account_id'],
                        'cost_center_id' => null,
                    ];
                })->all(),
                'tax'    => 0,
            ];

            /** @var Document $payment */
            $payment = $this->documents->storeDocument($payload);

            foreach ($allocations as $alloc) {
                $amount = (float) $alloc['amount'];
                // merge with existing allocation if present
                $existing = Allocation::where('document_id', $payment->id)
                    ->where('target_document_id', $alloc['document_id'])
                    ->first();

                if ($existing) {
                    $existing->increment('amount', $amount);
                    if (empty($existing->installment_id) && !empty($alloc['installment_id'])) {
                        $existing->installment_id = $alloc['installment_id'];
                        $existing->save();
                    }
                } else {
                    Allocation::create([
                        'document_id'        => $payment->id,
                        'target_document_id' => $alloc['document_id'],
                        'installment_id'     => $alloc['installment_id'] ?? null,
                        'amount'             => $amount,
                    ]);
                }

                $installment = ScheduledInstallment::lockForUpdate()->find($alloc['installment_id']);
                if (!$installment) {
                    continue;
                }

                $remaining = round((float) $installment->amount - $amount, 2);
                if ($remaining <= 0.01) {
                    $installment->update([
                        'amount'  => max($remaining, 0),
                        'paid'    => true,
                        'paid_at' => Carbon::now(),
                    ]);
                } else {
                    $installment->update([
                        'amount' => $remaining,
                        'paid'   => false,
                    ]);
                }
            }

            return $payment;
        });

        return redirect()
            ->route('documents.show', $document->id)
            ->with('ok', 'Orden de pago registrada correctamente.');
    }

    public function pdf(Document $document)
    {
        abort_unless($document->doctype === 'payment_order', 404);

    $document->load(['supplier', 'lines.account', 'allocations.targetDocument', 'allocations.installment']);

        $pdf = Pdf::loadView('payments.pdf', [
            'document' => $document,
            'supplier' => $document->supplier,
            'lines' => $document->lines,
            'allocations' => $document->allocations,
        ]);

        $filename = ($document->number ?: 'orden-pago') . '.pdf';

        return $pdf->stream($filename);
    }

    private function buildRetentionLines(Supplier $supplier, float $baseAmount): Collection
    {
        if ($baseAmount <= 0) {
            return collect();
        }

        return $supplier->taxes
            ->where('type', 'retention')
            ->where('applies_on', 'payment')
            ->map(function (Tax $tax) use ($baseAmount) {
                if (!$tax->account_id) {
                    throw new RuntimeException('El impuesto ' . $tax->code . ' no tiene una cuenta contable configurada.');
                }
                $effectiveBase = $baseAmount;
                $amount = round($effectiveBase * ($tax->rate / 100), 2);
                if ($amount <= 0) {
                    return null;
                }
                return [
                    'concept' => 'Retencion ' . $tax->name,
                    'account_id' => $tax->account_id,
                    'payment_method_id' => null,
                    'payment_method_label' => 'Retencion',
                    'amount' => $amount,
                ];
            })
            ->filter()
            ->values();
    }

    private function applyRetentionsToLines(Collection $lines, Collection $retentions): Collection
    {
        $retTotal = round($retentions->sum('amount'), 2);
        if ($retTotal <= 0) {
            return $lines;
        }

        $remaining = $retTotal;
        $adjusted = $lines->map(function (array $line) use (&$remaining) {
            $amount = round($line['amount'], 2);
            if ($remaining > 0 && $amount > 0) {
                $deduct = min($amount, $remaining);
                $line['amount'] = round($amount - $deduct, 2);
                $remaining = round($remaining - $deduct, 2);
            }
            return $line;
        });

        if ($remaining > 0.009) {
            throw new RuntimeException('El monto de los pagos es insuficiente para cubrir las retenciones calculadas.');
        }

        return $adjusted->filter(fn ($line) => $line['amount'] > 0.009)->values();
    }

    private function previewNextPaymentOrderNumber(): string
    {
        $last = Document::where('doctype', 'payment_order')->orderByDesc('id')->value('number');
        $sequence = $this->extractSequence($last) + 1;
        return $this->formatPaymentOrderNumber($sequence);
    }

    private function nextPaymentOrderNumberForUpdate(): string
    {
        $last = Document::where('doctype', 'payment_order')->lockForUpdate()->orderByDesc('id')->value('number');
        $sequence = $this->extractSequence($last) + 1;
        return $this->formatPaymentOrderNumber($sequence);
    }

    private function extractSequence(?string $number): int
    {
        if (!$number) {
            return 0;
        }
        if (preg_match('/(\d+)$/', $number, $matches)) {
            return (int) $matches[1];
        }
        return 0;
    }

    private function formatPaymentOrderNumber(int $sequence): string
    {
        return 'OP-' . str_pad((string) $sequence, 6, '0', STR_PAD_LEFT);
    }
}


