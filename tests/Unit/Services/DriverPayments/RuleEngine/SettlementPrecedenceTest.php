<?php

namespace Tests\Unit\Services\DriverPayments\RuleEngine;

use App\Services\DriverPayments\RuleEngine\DTOs\RuleConditionDefinition;
use App\Services\DriverPayments\RuleEngine\DTOs\RuleDefinition;
use App\Services\DriverPayments\RuleEngine\DTOs\SettlementContext;
use App\Services\DriverPayments\RuleEngine\Evaluators\ConditionEvaluator;
use App\Services\DriverPayments\RuleEngine\SettlementRuleEngine;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Services\DriverPayments\RuleEngine\Mocks\InMemoryRuleRepository;

class SettlementPrecedenceTest extends TestCase
{
    private SettlementRuleEngine $engine;
    private InMemoryRuleRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new InMemoryRuleRepository();
        $evaluator = new ConditionEvaluator();
        $this->engine = new SettlementRuleEngine($this->repository, $evaluator);
    }

    private function getDummyContext(): SettlementContext
    {
        return new SettlementContext(
            Carbon::now(), 1, 'test', 10, 'moto', 2020, 50, 100, 0, 20, false
        );
    }

    public function test_higher_priority_wins()
    {
        $this->repository->setRules([
            new RuleDefinition(1, 'Low Prio', 10, false, 'line', 'FIXED_AMOUNT', ['amount' => 100]),
            new RuleDefinition(2, 'High Prio', 100, false, 'line', 'FIXED_AMOUNT', ['amount' => 500]),
        ]);

        $breakdown = $this->engine->calculate($this->getDummyContext());
        $this->assertEquals(500, $breakdown->baseAmount);
        $this->assertEquals(2, $breakdown->appliedBaseRule['rule_id']);
    }

    public function test_more_conditions_wins_on_priority_tie()
    {
        $this->repository->setRules([
            new RuleDefinition(1, 'General', 100, false, 'line', 'FIXED_AMOUNT', ['amount' => 100], [
                new RuleConditionDefinition('vehicle_type', '=', 'moto')
            ]),
            new RuleDefinition(2, 'Specific', 100, false, 'line', 'FIXED_AMOUNT', ['amount' => 500], [
                new RuleConditionDefinition('vehicle_type', '=', 'moto'),
                new RuleConditionDefinition('zone_id', '=', 10)
            ]),
        ]);

        $breakdown = $this->engine->calculate($this->getDummyContext());
        $this->assertEquals(500, $breakdown->baseAmount);
        $this->assertEquals(2, $breakdown->appliedBaseRule['rule_id']);
    }

    public function test_higher_id_wins_on_absolute_tie()
    {
        $this->repository->setRules([
            new RuleDefinition(1, 'Old', 100, false, 'line', 'FIXED_AMOUNT', ['amount' => 100]),
            new RuleDefinition(2, 'New', 100, false, 'line', 'FIXED_AMOUNT', ['amount' => 500]),
        ]);

        $breakdown = $this->engine->calculate($this->getDummyContext());
        $this->assertEquals(500, $breakdown->baseAmount);
        $this->assertEquals(2, $breakdown->appliedBaseRule['rule_id']);
    }

    public function test_no_payment_rule_aborts_modifiers()
    {
        $this->repository->setRules([
            new RuleDefinition(1, 'No Payment', 999, false, 'line', 'NO_PAYMENT', []),
            new RuleDefinition(2, 'Plus', 100, true, 'line', 'FIXED_AMOUNT', ['amount' => 500]), // This should be ignored
        ]);

        $breakdown = $this->engine->calculate($this->getDummyContext());
        $this->assertEquals(0, $breakdown->totalAmount);
        $this->assertEmpty($breakdown->appliedModifiers);
        $this->assertTrue($breakdown->appliedBaseRule['trace']['terminated']);
    }

    public function test_modifiers_are_applied_correctly()
    {
        $this->repository->setRules([
            new RuleDefinition(1, 'Base', 100, false, 'line', 'FIXED_AMOUNT', ['amount' => 1000]),
            new RuleDefinition(2, 'Plus 1', 10, true, 'line', 'FIXED_AMOUNT', ['amount' => 200]),
            new RuleDefinition(3, 'Penalty', 10, true, 'line', 'FIXED_AMOUNT', ['amount' => -50]),
        ]);

        $breakdown = $this->engine->calculate($this->getDummyContext());
        $this->assertEquals(1000, $breakdown->baseAmount);
        $this->assertEquals(150, $breakdown->adjustmentsAmount);
        $this->assertEquals(1150, $breakdown->totalAmount);
        $this->assertCount(2, $breakdown->appliedModifiers);
    }
}
