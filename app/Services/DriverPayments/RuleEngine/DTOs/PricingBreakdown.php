<?php

namespace App\Services\DriverPayments\RuleEngine\DTOs;

class PricingBreakdown
{
    public float $totalAmount = 0.0;
    public float $baseAmount = 0.0;
    public float $adjustmentsAmount = 0.0;
    
    public ?array $appliedBaseRule = null;
    public array $appliedModifiers = [];

    public function setBaseRule(RuleDefinition $rule, ActionExecutionResult $result): void
    {
        $this->baseAmount = round($result->calculatedAmount, 2);
        $this->totalAmount = round($this->totalAmount + $this->baseAmount, 2);
        
        $this->appliedBaseRule = [
            'rule_id' => $rule->id,
            'name' => $rule->name,
            'action_type' => $rule->actionType,
            'calculated_amount' => $this->baseAmount,
            'trace' => $result->actionTrace,
        ];
    }

    public function addModifier(RuleDefinition $rule, ActionExecutionResult $result): void
    {
        $amount = round($result->calculatedAmount, 2);
        $this->adjustmentsAmount = round($this->adjustmentsAmount + $amount, 2);
        $this->totalAmount = round($this->totalAmount + $amount, 2);

        $this->appliedModifiers[] = [
            'rule_id' => $rule->id,
            'name' => $rule->name,
            'action_type' => $rule->actionType,
            'calculated_amount' => $amount,
            'trace' => $result->actionTrace,
        ];
    }

    public function setSyntheticComposite(array $trace, float $amount): void
    {
        $roundedAmount = round($amount, 2);
        $this->baseAmount = $roundedAmount;
        $this->totalAmount = $roundedAmount;
        $this->adjustmentsAmount = 0.0;
        $this->appliedModifiers = [];
        $this->appliedBaseRule = [
            'rule_id' => null,
            'name' => 'AUTO_COMPOSITE_CONCEPT',
            'action_type' => 'AUTO_COMPOSITE_CONCEPT',
            'calculated_amount' => $roundedAmount,
            'trace' => $trace,
        ];
    }

    public function toArray(): array
    {
        return [
            'total_amount' => $this->totalAmount,
            'base_amount' => $this->baseAmount,
            'adjustments_amount' => $this->adjustmentsAmount,
            'applied_base_rule' => $this->appliedBaseRule,
            'applied_modifiers' => $this->appliedModifiers,
        ];
    }
}
