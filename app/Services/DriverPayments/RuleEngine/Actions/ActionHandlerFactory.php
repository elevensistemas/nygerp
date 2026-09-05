<?php

namespace App\Services\DriverPayments\RuleEngine\Actions;

use App\Services\DriverPayments\RuleEngine\Actions\Contracts\ActionHandlerInterface;
use App\Services\DriverPayments\RuleEngine\Actions\Handlers\BasePlusMultiplierHandler;
use App\Services\DriverPayments\RuleEngine\Actions\Handlers\CompositeHandler;
use App\Services\DriverPayments\RuleEngine\Actions\Handlers\FixedAmountHandler;
use App\Services\DriverPayments\RuleEngine\Actions\Handlers\MultiplierHandler;
use App\Services\DriverPayments\RuleEngine\Actions\Handlers\NoPaymentHandler;
use App\Services\DriverPayments\RuleEngine\Actions\Handlers\ThresholdExcessHandler;
use App\Services\DriverPayments\RuleEngine\Actions\Handlers\ThresholdSwitchHandler;

class ActionHandlerFactory
{
    /**
     * Resolves the Action Handler based on the action type.
     */
    public static function resolve(string $actionType): ActionHandlerInterface
    {
        switch (strtoupper($actionType)) {
            case 'FIXED_AMOUNT':
                return new FixedAmountHandler();
            case 'MULTIPLIER':
                return new MultiplierHandler();
            case 'BASE_PLUS_MULTIPLIER':
                return new BasePlusMultiplierHandler();
            case 'THRESHOLD_EXCESS':
                return new ThresholdExcessHandler();
            case 'THRESHOLD_SWITCH':
                return new ThresholdSwitchHandler();
            case 'COMPOSITE':
                return new CompositeHandler();
            case 'NO_PAYMENT':
                return new NoPaymentHandler();
            default:
                throw new \InvalidArgumentException("Action handler for type [{$actionType}] not implemented.");
        }
    }
}
