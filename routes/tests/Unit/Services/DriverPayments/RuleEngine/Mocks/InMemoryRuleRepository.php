<?php

namespace Tests\Unit\Services\DriverPayments\RuleEngine\Mocks;

use App\Services\DriverPayments\RuleEngine\Contracts\SettlementRuleRepositoryInterface;
use App\Services\DriverPayments\RuleEngine\DTOs\RuleDefinition;

class InMemoryRuleRepository implements SettlementRuleRepositoryInterface
{
    /**
     * @var RuleDefinition[]
     */
    private array $rules = [];

    public function setRules(array $rules): void
    {
        $this->rules = $rules;
    }

    public function getActiveRules(?string $scope = null): array
    {
        return $this->rules;
    }
}
