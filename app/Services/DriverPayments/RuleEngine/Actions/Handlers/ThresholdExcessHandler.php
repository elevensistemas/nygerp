<?php

namespace App\Services\DriverPayments\RuleEngine\Actions\Handlers;

use App\Services\DriverPayments\RuleEngine\Actions\Contracts\ActionHandlerInterface;
use App\Services\DriverPayments\RuleEngine\DTOs\ActionExecutionResult;
use App\Services\DriverPayments\RuleEngine\DTOs\SettlementContext;
use App\Services\DriverPayments\RuleEngine\Exceptions\ActionPayloadValidationException;
use App\Services\DriverPayments\RuleEngine\SettlementRuleEngine;

class ThresholdExcessHandler implements ActionHandlerInterface
{
    public function execute(array $payload, SettlementContext $context, SettlementRuleEngine $engine): ActionExecutionResult
    {
        $targetField = $payload['target_field'] ?? $payload['evaluate_field'] ?? null;
        $thresholdQty = $payload['threshold_qty'] ?? $payload['threshold_limit'] ?? null;
        $baseAmount = $payload['base_amount'] ?? 0;
        $excessRate = $payload['excess_rate'] ?? $payload['excess_multiplier_rate'] ?? null;

        if ($targetField === null || $thresholdQty === null || $excessRate === null) {
            throw new ActionPayloadValidationException("Missing 'target_field', 'threshold_qty', or 'excess_rate' in THRESHOLD_EXCESS payload.");
        }

        $baseAmount = (float) $baseAmount;
        $thresholdQty = (float) $thresholdQty;
        $excessRate = (float) $excessRate;
        
        $qty = (float) $context->getFieldValue($targetField);

        $excessQty = max(0, $qty - $thresholdQty);
        $excessAmount = $excessQty * $excessRate;
        $totalAmount = $baseAmount + $excessAmount;

        return new ActionExecutionResult($totalAmount, [
            'base_amount' => $baseAmount,
            'target_field' => $targetField,
            'qty' => $qty,
            'threshold_qty' => $thresholdQty,
            'excess_qty' => $excessQty,
            'excess_rate' => $excessRate,
            'excess_amount' => $excessAmount,
        ]);
    }
}
