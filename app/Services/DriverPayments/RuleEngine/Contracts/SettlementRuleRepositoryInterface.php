<?php

namespace App\Services\DriverPayments\RuleEngine\Contracts;

use App\Services\DriverPayments\RuleEngine\DTOs\RuleDefinition;

interface SettlementRuleRepositoryInterface
{
    /**
     * Gets all active rules mapped to DTOs.
     *
     * @return RuleDefinition[]
     */
    public function getActiveRules(?string $scope = null): array;
}
