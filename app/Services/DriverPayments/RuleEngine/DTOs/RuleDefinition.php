<?php

namespace App\Services\DriverPayments\RuleEngine\DTOs;

class RuleDefinition
{
    public int $id;
    public string $name;
    public int $priority;
    public bool $isModifier;
    public string $scope;
    public string $actionType;
    public array $actionPayload;
    
    /** @var RuleConditionDefinition[] */
    public array $conditions;

    public function __construct(
        int $id,
        string $name,
        int $priority,
        bool $isModifier,
        string $scope,
        string $actionType,
        array $actionPayload,
        array $conditions = []
    ) {
        $this->id = $id;
        $this->name = $name;
        $this->priority = $priority;
        $this->isModifier = $isModifier;
        $this->scope = $scope;
        $this->actionType = $actionType;
        $this->actionPayload = $actionPayload;
        $this->conditions = $conditions;
    }
}
