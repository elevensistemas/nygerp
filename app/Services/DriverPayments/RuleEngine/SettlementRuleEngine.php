<?php

namespace App\Services\DriverPayments\RuleEngine;

use App\Services\DriverPayments\RuleEngine\Actions\ActionHandlerFactory;
use App\Services\DriverPayments\RuleEngine\Contracts\SettlementRuleRepositoryInterface;
use App\Services\DriverPayments\RuleEngine\DTOs\ActionExecutionResult;
use App\Services\DriverPayments\RuleEngine\DTOs\PricingBreakdown;
use App\Services\DriverPayments\RuleEngine\DTOs\RuleDefinition;
use App\Services\DriverPayments\RuleEngine\DTOs\SettlementContext;
use App\Services\DriverPayments\RuleEngine\Evaluators\ConditionEvaluator;
use App\Services\DriverPayments\RuleEngine\Exceptions\CyclicRuleException;
use App\Services\DriverPayments\RuleEngine\Formatting\SettlementCompositeConceptParser;
use App\Services\DriverPayments\RuleEngine\Formatting\SettlementConceptNormalizer;

class SettlementRuleEngine
{
    private SettlementRuleRepositoryInterface $repository;
    private ConditionEvaluator $evaluator;

    /**
     * @var RuleDefinition[]
     */
    private array $rulesCache = [];
    
    /**
     * Tracks the recursion tree for composite rules to detect infinite loops.
     */
    private array $evaluationStack = [];

    public function __construct(
        SettlementRuleRepositoryInterface $repository,
        ConditionEvaluator $evaluator
    ) {
        $this->repository = $repository;
        $this->evaluator = $evaluator;
    }

    public function calculate(SettlementContext $context, string $scope = 'line'): PricingBreakdown
    {
        $this->evaluationStack = [];
        $this->loadRules($scope);

        return $this->calculateInternal($context, $scope);
    }

    public function evaluateScopedRules(SettlementContext $context, string $scope = 'receipt', bool $allowModifiersWithoutBase = true): array
    {
        $this->evaluationStack = [];
        $this->loadRules($scope);

        $matchingRules = $this->matchingRules($context, $scope);
        $evaluated = [];

        if (! empty($matchingRules['base'])) {
            foreach ($matchingRules['base'] as $baseRule) {
                $this->evaluationStack[] = $baseRule->id;

                $baseHandler = ActionHandlerFactory::resolve($baseRule->actionType);
                $baseResult = $baseHandler->execute($baseRule->actionPayload, $context, $this);

                $evaluated[] = [
                    'role' => 'base',
                    'rule' => $baseRule,
                    'result' => $baseResult,
                ];

                array_pop($this->evaluationStack);
            }
        }

        if (! empty($matchingRules['base']) || $allowModifiersWithoutBase) {
            foreach ($matchingRules['modifiers'] as $modifierRule) {
                $this->evaluationStack[] = $modifierRule->id;

                $modifierHandler = ActionHandlerFactory::resolve($modifierRule->actionType);
                $modResult = $modifierHandler->execute($modifierRule->actionPayload, $context, $this);

                $evaluated[] = [
                    'role' => 'modifier',
                    'rule' => $modifierRule,
                    'result' => $modResult,
                ];

                array_pop($this->evaluationStack);
            }
        }

        return $evaluated;
    }

    private function calculateInternal(SettlementContext $context, string $scope = 'line'): PricingBreakdown
    {
        $this->loadRules($scope);

        $breakdown = new PricingBreakdown();

        $matchingRules = $this->matchingRules($context, $scope);
        $baseRules = $matchingRules['base'];
        $modifierRules = $matchingRules['modifiers'];

        $parsedComposite = SettlementCompositeConceptParser::parse($context->concept_name);

        if (empty($baseRules)) {
            if ($parsedComposite !== null) {
                return $this->calculateParsedComposite($context, $parsedComposite, $scope);
            }

            // No base rule applies. Return empty breakdown of 0.
            return $breakdown;
        }

        if ($parsedComposite !== null && ! $this->hasExplicitConceptMatch($baseRules, $context)) {
            return $this->calculateParsedComposite($context, $parsedComposite, $scope);
        }

        $winnerBaseRule = $this->sortedBaseRules($baseRules)[0];

        $this->evaluationStack[] = $winnerBaseRule->id;
        
        $baseHandler = ActionHandlerFactory::resolve($winnerBaseRule->actionType);
        $baseResult = $baseHandler->execute($winnerBaseRule->actionPayload, $context, $this);
        
        $breakdown->setBaseRule($winnerBaseRule, $baseResult);

        if ($winnerBaseRule->actionType === 'NO_PAYMENT') {
            return $breakdown;
        }

        foreach ($modifierRules as $modifierRule) {
            $this->evaluationStack[] = $modifierRule->id;
            
            $modifierHandler = ActionHandlerFactory::resolve($modifierRule->actionType);
            $modResult = $modifierHandler->execute($modifierRule->actionPayload, $context, $this);
            
            $breakdown->addModifier($modifierRule, $modResult);
            
            array_pop($this->evaluationStack);
        }

        return $breakdown;
    }

    private function calculateParsedComposite(SettlementContext $context, array $parsedComposite, string $scope = 'line'): PricingBreakdown
    {
        $breakdown = new PricingBreakdown();
        $children = [];
        $totalAmount = 0.0;

        foreach ($parsedComposite['terms'] as $term) {
            $termContext = $context->withConceptName($term['concept_name']);
            $termBreakdown = $this->calculateInternal($termContext, $scope);
            $baseAmount = round((float) $termBreakdown->totalAmount, 2);
            $appliedAmount = round($baseAmount * (float) $term['weight'], 2);

            $children[] = [
                'concept_name' => $term['concept_name'],
                'concept_key' => $term['concept_key'],
                'weight' => (float) $term['weight'],
                'base_result' => $baseAmount,
                'applied_result' => $appliedAmount,
                'breakdown' => $termBreakdown->toArray(),
            ];

            $totalAmount += $appliedAmount;
        }

        $breakdown->setSyntheticComposite([
            'expression' => $parsedComposite['expression'],
            'evaluated_children' => $children,
        ], $totalAmount);

        return $breakdown;
    }

    private function hasExplicitConceptMatch(array $rules, SettlementContext $context): bool
    {
        $currentConceptKey = SettlementConceptNormalizer::normalize((string) ($context->concept_name ?? ''));
        if ($currentConceptKey === '') {
            return false;
        }

        foreach ($rules as $rule) {
            foreach ($rule->conditions as $condition) {
                if ($condition->field !== 'concept_key') {
                    continue;
                }

                if (! in_array(strtoupper($condition->operator), ['=', '=='], true)) {
                    continue;
                }

                if (SettlementConceptNormalizer::normalize((string) $condition->value) === $currentConceptKey) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Executes a specific rule directly, typically invoked from inside a Composite rule.
     * Bypasses condition checks (assumes the parent caller ensures it must run).
     */
    public function evaluateSpecificRule(int $ruleId, SettlementContext $context): ?ActionExecutionResult
    {
        $this->loadRules('line');

        if (in_array($ruleId, $this->evaluationStack, true)) {
            throw new CyclicRuleException("Infinite loop detected: Rule ID {$ruleId} references itself in the execution stack.");
        }

        $rule = $this->findRuleById($ruleId);
        if (!$rule) {
            return null;
        }

        $this->evaluationStack[] = $ruleId;
        
        $handler = ActionHandlerFactory::resolve($rule->actionType);
        $result = $handler->execute($rule->actionPayload, $context, $this);
        
        array_pop($this->evaluationStack);

        return $result;
    }

    private function loadRules(string $scope = 'line'): void
    {
        if (! array_key_exists($scope, $this->rulesCache)) {
            $this->rulesCache[$scope] = $this->repository->getActiveRules($scope);
        }
    }

    private function findRuleById(int $ruleId): ?RuleDefinition
    {
        foreach ($this->rulesCache as $scopeRules) {
            foreach ($scopeRules as $rule) {
                if ($rule->id === $ruleId) {
                    return $rule;
                }
            }
        }
        return null;
    }

    private function matchingRules(SettlementContext $context, string $scope): array
    {
        $applicableRules = array_filter($this->rulesCache[$scope] ?? [], function (RuleDefinition $rule) use ($context) {
            return $this->evaluator->matches($rule->conditions, $context);
        });

        $baseRules = [];
        $modifierRules = [];

        foreach ($applicableRules as $rule) {
            if ($rule->isModifier) {
                $modifierRules[] = $rule;
            } else {
                $baseRules[] = $rule;
            }
        }

        return [
            'base' => $baseRules,
            'modifiers' => $modifierRules,
        ];
    }

    private function sortedBaseRules(array $baseRules): array
    {
        usort($baseRules, function (RuleDefinition $a, RuleDefinition $b) {
            if ($a->priority !== $b->priority) {
                return $b->priority <=> $a->priority;
            }

            $countA = count($a->conditions);
            $countB = count($b->conditions);
            if ($countA !== $countB) {
                return $countB <=> $countA;
            }

            return $b->id <=> $a->id;
        });

        return $baseRules;
    }
}
