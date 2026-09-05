<?php

namespace App\Services\DriverPayments\RuleEngine\Actions\Contracts;

use App\Services\DriverPayments\RuleEngine\DTOs\ActionExecutionResult;
use App\Services\DriverPayments\RuleEngine\DTOs\SettlementContext;
use App\Services\DriverPayments\RuleEngine\SettlementRuleEngine;

interface ActionHandlerInterface
{
    /**
     * Executes the specific business logic for the given action type.
     *
     * @param array $payload The JSON configuration specific to this handler.
     * @param SettlementContext $context The trip details.
     * @param SettlementRuleEngine $engine For recursive calls in Composite handlers.
     * 
     * @return ActionExecutionResult A strongly-typed result containing amount and trace.
     * @throws \App\Services\DriverPayments\RuleEngine\Exceptions\ActionPayloadValidationException
     */
    public function execute(array $payload, SettlementContext $context, SettlementRuleEngine $engine): ActionExecutionResult;
}
