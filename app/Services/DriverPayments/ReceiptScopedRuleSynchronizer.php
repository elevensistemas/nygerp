<?php

namespace App\Services\DriverPayments;

use App\Models\DriverPaymentConcept;
use App\Models\ReciboChofer;
use App\Models\ReciboChoferItem;
use App\Services\DriverPayments\RuleEngine\DTOs\SettlementContext;
use App\Services\DriverPayments\RuleEngine\SettlementRuleEngine;
use Carbon\Carbon;

class ReceiptScopedRuleSynchronizer
{
    private SettlementRuleEngine $ruleEngine;

    public function __construct(
        SettlementRuleEngine $ruleEngine
    ) {
        $this->ruleEngine = $ruleEngine;
    }

    public function sync(ReciboChofer $recibo): int
    {
        $recibo->items()->where('source_key', 'like', 'receipt-rule:%')->delete();

        $sourceItems = $recibo->items()
            ->get()
            ->filter(function (ReciboChoferItem $item) {
                $meta = is_array($item->meta) ? $item->meta : [];

                return ($meta['manual'] ?? false) !== true
                    && ($meta['auto_adjustment'] ?? false) !== true
                    && ($meta['receipt_rule'] ?? false) !== true
                    && ! str_starts_with((string) ($item->source_key ?? ''), 'receipt-rule:');
            })
            ->values();

        if ($sourceItems->isEmpty()) {
            return 0;
        }

        $context = $this->buildContext($recibo, $sourceItems);
        $evaluatedRules = $this->ruleEngine->evaluateScopedRules($context, 'receipt', true);

        foreach ($evaluatedRules as $entry) {
            $rule = $entry['rule'];
            $result = $entry['result'];
            $receiptConcept = trim((string) ($rule->actionPayload['receipt_concept'] ?? ''));
            $itemConcept = $receiptConcept !== '' ? $receiptConcept : $rule->name;
            $amount = $this->applyConceptSign($itemConcept, round((float) $result->calculatedAmount, 2));

            if ($amount === 0.0) {
                continue;
            }

            ReciboChoferItem::create([
                'recibo_chofer_id' => $recibo->id,
                'concepto' => $itemConcept,
                'cantidad' => 1,
                'zona' => $sourceItems->pluck('zona')->filter()->unique()->count() === 1 ? (string) $sourceItems->pluck('zona')->filter()->first() : null,
                'importe_unitario' => $amount,
                'importe' => $amount,
                'source_key' => 'receipt-rule:' . $recibo->id . ':' . $rule->id,
                'meta' => [
                    'receipt_rule' => true,
                    'rule_scope' => 'receipt',
                    'rule_role' => $entry['role'],
                    'settlement_rule_id' => $rule->id,
                    'configured_receipt_concept' => $itemConcept,
                    'vehicle_type' => $context->vehicle_type,
                    'traffic_zone_id' => $context->zone_id,
                    'source_transportista_id' => $recibo->transportista_id,
                    'period_start' => optional($context->period_start)->toDateString(),
                    'period_end' => optional($context->period_end)->toDateString(),
                    'pricing_breakdown' => [
                        'total_amount' => $amount,
                        'base_amount' => $entry['role'] === 'base' ? $amount : 0,
                        'adjustments_amount' => $entry['role'] === 'modifier' ? $amount : 0,
                        'applied_base_rule' => $entry['role'] === 'base' ? [
                            'id' => $rule->id,
                            'name' => $rule->name,
                            'action_type' => $rule->actionType,
                            'calculated_amount' => $amount,
                            'action_trace' => $result->actionTrace,
                        ] : null,
                        'applied_modifiers' => $entry['role'] === 'modifier' ? [[
                            'id' => $rule->id,
                            'name' => $rule->name,
                            'action_type' => $rule->actionType,
                            'calculated_amount' => $amount,
                            'action_trace' => $result->actionTrace,
                        ]] : [],
                    ],
                ],
            ]);
        }

        return count($evaluatedRules);
    }

    private function buildContext(ReciboChofer $recibo, $items): SettlementContext
    {
        $lastItem = $items
            ->sortBy(function (ReciboChoferItem $item) {
                $date = data_get($item->meta, 'fecha');

                try {
                    return ($date ? Carbon::parse($date) : now())->timestamp;
                } catch (\Throwable $e) {
                    return 0;
                }
            })
            ->last();

        $tripDate = $lastItem && data_get($lastItem->meta, 'fecha')
            ? Carbon::parse(data_get($lastItem->meta, 'fecha'))
            : ($recibo->periodo_desde ? $recibo->periodo_desde->copy() : ($recibo->fecha_emision ? $recibo->fecha_emision->copy() : now()));

        $lastZoneId = $lastItem ? data_get($lastItem->meta, 'traffic_zone_id') : null;
        $lastModelYear = $lastItem ? data_get($lastItem->meta, 'model_year') : null;
        $lastVehicleType = $lastItem ? trim((string) data_get($lastItem->meta, 'vehicle_type', 'general')) : 'general';
        $lastConceptName = $lastItem ? trim((string) ($lastItem->concepto ?: data_get($lastItem->meta, 'zona') ?: 'RECIBO')) : 'RECIBO';
        $lastIsRemoteZone = (bool) ($lastItem ? data_get($lastItem->meta, 'zona_lejana', false) : false);

        return new SettlementContext(
            $tripDate,
            (int) $recibo->transportista_id,
            $lastConceptName !== '' ? $lastConceptName : 'RECIBO',
            $lastZoneId !== null && $lastZoneId !== '' ? (int) $lastZoneId : null,
            $lastVehicleType !== '' ? $lastVehicleType : 'general',
            $lastModelYear !== null && $lastModelYear !== '' ? (int) $lastModelYear : null,
            round((float) $items->sum(fn (ReciboChoferItem $item) => (float) data_get($item->meta, 'kilometros', 0)), 2),
            round((float) $items->sum(fn (ReciboChoferItem $item) => (float) ($item->entregados ?? data_get($item->meta, 'entregados', 0))), 2),
            round((float) $items->sum(fn (ReciboChoferItem $item) => (float) data_get($item->meta, 'paquetes_ausentes', 0)), 2),
            round((float) $items->sum(fn (ReciboChoferItem $item) => (float) ($item->paradas ?? data_get($item->meta, 'paradas', 0))), 2),
            $lastIsRemoteZone,
            $recibo->fecha_emision ? $recibo->fecha_emision->copy() : null,
            $recibo->periodo_desde ? $recibo->periodo_desde->copy() : null,
            $recibo->periodo_hasta ? $recibo->periodo_hasta->copy() : null,
        );
    }

    private function applyConceptSign(string $conceptName, float $amount): float
    {
        $concept = DriverPaymentConcept::query()
            ->whereRaw('LOWER(name) = ?', [mb_strtolower(trim($conceptName))])
            ->first();

        if (! $concept || (int) $concept->sign !== -1) {
            return $amount;
        }

        return abs($amount) * -1;
    }
}
