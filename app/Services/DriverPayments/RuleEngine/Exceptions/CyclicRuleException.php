<?php

namespace App\Services\DriverPayments\RuleEngine\Exceptions;

use Exception;

class CyclicRuleException extends Exception
{
    // Thrown when a Composite rule causes an infinite loop by referencing itself
}
