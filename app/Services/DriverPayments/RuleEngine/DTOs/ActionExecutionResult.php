<?php

namespace App\Services\DriverPayments\RuleEngine\DTOs;

class ActionExecutionResult
{
    public float $calculatedAmount;
    public array $actionTrace;

    public function __construct(float $calculatedAmount, array $actionTrace = [])
    {
        $this->calculatedAmount = $calculatedAmount;
        $this->actionTrace = $actionTrace;
    }
}
