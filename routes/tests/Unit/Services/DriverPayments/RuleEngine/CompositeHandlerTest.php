<?php

namespace Tests\Unit\Services\DriverPayments\RuleEngine;

use App\Services\DriverPayments\RuleEngine\DTOs\RuleDefinition;
use App\Services\DriverPayments\RuleEngine\DTOs\SettlementContext;
use App\Services\DriverPayments\RuleEngine\Evaluators\ConditionEvaluator;
use App\Services\DriverPayments\RuleEngine\Exceptions\CyclicRuleException;
use App\Services\DriverPayments\RuleEngine\SettlementRuleEngine;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Services\DriverPayments\RuleEngine\Mocks\InMemoryRuleRepository;

class CompositeHandlerTest extends TestCase
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

    public function test_composite_rule_resolves_weighed_sum()
    {
        $this->repository->setRules([
            // ID 1: 50% Rule 2, 50% Rule 3
            new RuleDefinition(1, 'Composite', 100, false, 'line', 'COMPOSITE', [
                'sub_rules' => [
                    ['rule_id' => 2, 'weight' => 0.5],
                    ['rule_id' => 3, 'weight' => 0.5],
                ]
            ]),
            new RuleDefinition(2, 'Sub1', 10, false, 'line', 'FIXED_AMOUNT', ['amount' => 1000]),
            new RuleDefinition(3, 'Sub2', 10, false, 'line', 'FIXED_AMOUNT', ['amount' => 2000]),
        ]);

        $breakdown = $this->engine->calculate($this->getDummyContext());
        
        // 1000 * 0.5 = 500
        // 2000 * 0.5 = 1000
        // Total Base = 1500
        $this->assertEquals(1500, $breakdown->baseAmount);
        $this->assertCount(2, $breakdown->appliedBaseRule['trace']['evaluated_children']);
    }

    public function test_composite_throws_cyclic_exception_when_self_referenced()
    {
        $this->repository->setRules([
            // Rule 1 points to Rule 2, Rule 2 points back to Rule 1
            new RuleDefinition(1, 'Comp 1', 100, false, 'line', 'COMPOSITE', [
                'sub_rules' => [
                    ['rule_id' => 2, 'weight' => 1],
                ]
            ]),
            new RuleDefinition(2, 'Comp 2', 10, false, 'line', 'COMPOSITE', [
                'sub_rules' => [
                    ['rule_id' => 1, 'weight' => 1],
                ]
            ]),
        ]);

        $this->expectException(CyclicRuleException::class);
        $this->engine->calculate($this->getDummyContext());
    }
}
