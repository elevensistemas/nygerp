<?php

namespace App\Services\DriverPayments\RuleEngine\Actions\Handlers;

use App\Services\DriverPayments\RuleEngine\Actions\Contracts\ActionHandlerInterface;
use App\Services\DriverPayments\RuleEngine\DTOs\ActionExecutionResult;
use App\Services\DriverPayments\RuleEngine\DTOs\SettlementContext;
use App\Services\DriverPayments\RuleEngine\Exceptions\ActionPayloadValidationException;
use App\Services\DriverPayments\RuleEngine\SettlementRuleEngine;

class CompositeHandler implements ActionHandlerInterface
{
    public function execute(array $payload, SettlementContext $context, SettlementRuleEngine $engine): ActionExecutionResult
    {
        if (!isset($payload['sub_rules']) || !is_array($payload['sub_rules'])) {
            throw new ActionPayloadValidationException("Missing 'sub_rules' array in COMPOSITE payload.");
        }

        $totalAmount = 0.0;
        $evaluatedChildren = [];

        foreach ($payload['sub_rules'] as $subRuleCfg) {
            if (!isset($subRuleCfg['rule_id']) || !isset($subRuleCfg['weight'])) {
                continue;
            }

            $ruleId = (int) $subRuleCfg['rule_id'];
            $weight = (float) $subRuleCfg['weight'];

            // Ask the engine to evaluate the subrule directly (Base execution only)
            // Note: The engine should expose a method `evaluateSpecificRule` to do this bypassing the normal flow.
            $subResult = $engine->evaluateSpecificRule($ruleId, $context);
            
            if ($subResult !== null) {
                $appliedResult = $subResult->calculatedAmount * $weight;
                $totalAmount += $appliedResult;

                $evaluatedChildren[] = [
                    'rule_id' => $ruleId,
                    'weight' => $weight,
                    'base_result' => $subResult->calculatedAmount,
                    'applied_result' => $appliedResult,
                ];
            }
        }

        return new ActionExecutionResult($totalAmount, [
            'evaluated_children' => $evaluatedChildren,
        ]);
    }
}
