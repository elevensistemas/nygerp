<?php

namespace App\Services\DriverPayments\RuleEngine\Exceptions;

use Exception;

class ActionPayloadValidationException extends Exception
{
    // Thrown when an Action's payload is missing required keys
}
