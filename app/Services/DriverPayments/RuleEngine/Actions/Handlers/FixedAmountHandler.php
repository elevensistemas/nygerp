<?php

namespace App\Services\DriverPayments\RuleEngine\Actions\Handlers;

use App\Services\DriverPayments\RuleEngine\Actions\Contracts\ActionHandlerInterface;
use App\Services\DriverPayments\RuleEngine\DTOs\ActionExecutionResult;
use App\Services\DriverPayments\RuleEngine\DTOs\SettlementContext;
use App\Services\DriverPayments\RuleEngine\Exceptions\ActionPayloadValidationException;
use App\Services\DriverPayments\RuleEngine\SettlementRuleEngine;

class FixedAmountHandler implements ActionHandlerInterface
{
    public function execute(array $payload, SettlementContext $context, SettlementRuleEngine $engine): ActionExecutionResult
    {
        if (!isset($payload['amount'])) {
            throw new ActionPayloadValidationException("Missing 'amount' in FIXED_AMOUNT payload.");
        }

        $amount = (float) $payload['amount'];

        return new ActionExecutionResult($amount, [
            'fixed_applied' => $amount,
        ]);
    }
}
