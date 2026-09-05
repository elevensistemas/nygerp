<?php

namespace App\Services\DriverPayments\RuleEngine\Actions\Handlers;

use App\Services\DriverPayments\RuleEngine\Actions\Contracts\ActionHandlerInterface;
use App\Services\DriverPayments\RuleEngine\DTOs\ActionExecutionResult;
use App\Services\DriverPayments\RuleEngine\DTOs\SettlementContext;
use App\Services\DriverPayments\RuleEngine\Exceptions\ActionPayloadValidationException;
use App\Services\DriverPayments\RuleEngine\SettlementRuleEngine;

class MultiplierHandler implements ActionHandlerInterface
{
    public function execute(array $payload, SettlementContext $context, SettlementRuleEngine $engine): ActionExecutionResult
    {
        $targetField = $payload['target_field'] ?? $payload['multiply_by_field'] ?? null;
        $rate = $payload['rate'] ?? $payload['multiplier_rate'] ?? $payload['multiplier'] ?? null;

        if ($targetField === null || $rate === null) {
            throw new ActionPayloadValidationException("Missing 'target_field' or 'rate' in MULTIPLIER payload.");
        }

        $rate = (float) $rate;
        $qty = (float) $context->getFieldValue($targetField);
        $offset = (float) ($payload['field_offset'] ?? 0);
        $effectiveQty = max(0, $qty - $offset);

        $amount = $rate * $effectiveQty;

        return new ActionExecutionResult($amount, [
            'target_field' => $targetField,
            'qty' => $qty,
            'field_offset' => $offset,
            'effective_qty' => $effectiveQty,
            'rate' => $rate,
            'amount' => $amount,
        ]);
    }
}
