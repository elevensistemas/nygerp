<?php

namespace Tests\Unit\Services\DriverPayments\RuleEngine;

use App\Services\DriverPayments\RuleEngine\DTOs\RuleConditionDefinition;
use App\Services\DriverPayments\RuleEngine\DTOs\SettlementContext;
use App\Services\DriverPayments\RuleEngine\Evaluators\ConditionEvaluator;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class ConditionEvaluatorTest extends TestCase
{
    private ConditionEvaluator $evaluator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->evaluator = new ConditionEvaluator();
    }

    private function getDummyContext(): SettlementContext
    {
        return new SettlementContext(
            Carbon::now(),
            1,
            'test_concept',
            10,
            'moto',
            2020,
            50.5,
            100.0,
            5.0,
            20.0,
            false
        );
    }

    public function test_evaluator_handles_equal_operator()
    {
        $context = $this->getDummyContext();

        $conditionPass = new RuleConditionDefinition('vehicle_type', '=', 'moto');
        $this->assertTrue($this->evaluator->matches([$conditionPass], $context));

        $conditionFail = new RuleConditionDefinition('vehicle_type', '=', 'camioneta');
        $this->assertFalse($this->evaluator->matches([$conditionFail], $context));
    }

    public function test_evaluator_handles_numeric_comparisons()
    {
        $context = $this->getDummyContext();

        $cond1 = new RuleConditionDefinition('kilometers', '>', 50);
        $cond2 = new RuleConditionDefinition('kilometers', '>=', 50.5);
        $cond3 = new RuleConditionDefinition('delivered_packages', '<', 101);

        $this->assertTrue($this->evaluator->matches([$cond1, $cond2, $cond3], $context));

        $condFail = new RuleConditionDefinition('kilometers', '<', 50);
        $this->assertFalse($this->evaluator->matches([$condFail], $context));
    }

    public function test_evaluator_handles_in_operator()
    {
        $context = $this->getDummyContext();

        $conditionPass = new RuleConditionDefinition('zone_id', 'IN', [5, 10, 15]);
        $this->assertTrue($this->evaluator->matches([$conditionPass], $context));

        $conditionFail = new RuleConditionDefinition('zone_id', 'IN', [1, 2]);
        $this->assertFalse($this->evaluator->matches([$conditionFail], $context));
    }

    public function test_evaluator_handles_contains_operator()
    {
        $context = $this->getDummyContext();
        $context->carrier_name = 'Transportes San Juan';

        $conditionPass1 = new RuleConditionDefinition('concept_name', 'CONTAINS', 'concept');
        $this->assertTrue($this->evaluator->matches([$conditionPass1], $context));

        $conditionPass2 = new RuleConditionDefinition('concept_name', 'CONTAINS', 'TEST_CON');
        $this->assertTrue($this->evaluator->matches([$conditionPass2], $context));

        $conditionPass3 = new RuleConditionDefinition('concept_name', 'CONTAINS', '%concept%');
        $this->assertTrue($this->evaluator->matches([$conditionPass3], $context));

        $conditionCarrier = new RuleConditionDefinition('carrier_name', 'CONTAINS', 'san juan');
        $this->assertTrue($this->evaluator->matches([$conditionCarrier], $context));

        $conditionFail = new RuleConditionDefinition('concept_name', 'CONTAINS', 'camioneta');
        $this->assertFalse($this->evaluator->matches([$conditionFail], $context));
    }
}
