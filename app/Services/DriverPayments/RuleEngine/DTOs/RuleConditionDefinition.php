<?php

namespace App\Services\DriverPayments\RuleEngine\DTOs;

class RuleConditionDefinition
{
    public string $field;
    public string $operator;
    /** @var mixed */
    public $value;

    public function __construct(string $field, string $operator, $value)
    {
        $this->field = $field;
        $this->operator = $operator;
        $this->value = $value;
    }
}
