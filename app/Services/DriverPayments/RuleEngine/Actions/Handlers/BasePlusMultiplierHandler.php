<?php

namespace App\Services\DriverPayments\RuleEngine\Actions\Handlers;

use App\Services\DriverPayments\RuleEngine\Actions\Contracts\ActionHandlerInterface;
use App\Services\DriverPayments\RuleEngine\DTOs\ActionExecutionResult;
use App\Services\DriverPayments\RuleEngine\DTOs\SettlementContext;
use App\Services\DriverPayments\RuleEngine\Exceptions\ActionPayloadValidationException;
use App\Services\DriverPayments\RuleEngine\SettlementRuleEngine;

class BasePlusMultiplierHandler implements ActionHandlerInterface
{
    public function execute(array $payload, SettlementContext $context, SettlementRuleEngine $engine): ActionExecutionResult
    {
        $targetField = $payload['target_field'] ?? $payload['multiply_by_field'] ?? null;
        $rate = $payload['rate'] ?? $payload['multiplier_rate'] ?? $payload['multiplier'] ?? null;

        if (!isset($payload['base_amount']) || $targetField === null || $rate === null) {
            throw new ActionPayloadValidationException("Missing 'base_amount', 'target_field', or 'rate' in BASE_PLUS_MULTIPLIER payload.");
        }

        $baseAmount = (float) $payload['base_amount'];
        $rate = (float) $rate;
        $qty = (float) $context->getFieldValue($targetField);
        $offset = (float) ($payload['field_offset'] ?? 0);
        $effectiveQty = max(0, $qty - $offset);

        $variableAmount = $rate * $effectiveQty;
        $totalAmount = $baseAmount + $variableAmount;

        return new ActionExecutionResult($totalAmount, [
            'base_amount' => $baseAmount,
            'target_field' => $targetField,
            'qty' => $qty,
            'field_offset' => $offset,
            'effective_qty' => $effectiveQty,
            'rate' => $rate,
            'variable_amount' => $variableAmount,
        ]);
    }
}
