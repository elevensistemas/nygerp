<?php

namespace App\Services\DriverPayments\RuleEngine\Actions\Handlers;

use App\Services\DriverPayments\RuleEngine\Actions\Contracts\ActionHandlerInterface;
use App\Services\DriverPayments\RuleEngine\DTOs\ActionExecutionResult;
use App\Services\DriverPayments\RuleEngine\DTOs\SettlementContext;
use App\Services\DriverPayments\RuleEngine\Exceptions\ActionPayloadValidationException;
use App\Services\DriverPayments\RuleEngine\SettlementRuleEngine;

class ThresholdSwitchHandler implements ActionHandlerInterface
{
    public function execute(array $payload, SettlementContext $context, SettlementRuleEngine $engine): ActionExecutionResult
    {
        $requiredKeys = ['target_field', 'threshold_qty', 'base_amount', 'passed_rate'];
        foreach ($requiredKeys as $key) {
            if (!isset($payload[$key])) {
                throw new ActionPayloadValidationException("Missing '{$key}' in THRESHOLD_SWITCH payload.");
            }
        }

        $baseAmount = (float) $payload['base_amount'];
        $thresholdQty = (float) $payload['threshold_qty'];
        $passedRate = (float) $payload['passed_rate'];
        
        $qty = (float) $context->getFieldValue($payload['target_field']);

        if ($qty > $thresholdQty) {
            $totalAmount = $qty * $passedRate;
            $appliedSwitch = true;
        } else {
            $totalAmount = $baseAmount;
            $appliedSwitch = false;
        }

        return new ActionExecutionResult($totalAmount, [
            'target_field' => $payload['target_field'],
            'qty' => $qty,
            'threshold_qty' => $thresholdQty,
            'applied_switch' => $appliedSwitch,
            'base_amount' => $baseAmount,
            'passed_rate' => $passedRate,
        ]);
    }
}
