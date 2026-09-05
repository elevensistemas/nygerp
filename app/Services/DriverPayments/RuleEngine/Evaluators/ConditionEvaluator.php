<?php

namespace App\Services\DriverPayments\RuleEngine\Evaluators;

use App\Services\DriverPayments\RuleEngine\DTOs\RuleConditionDefinition;
use App\Services\DriverPayments\RuleEngine\DTOs\SettlementContext;
use App\Services\DriverPayments\RuleEngine\Formatting\SettlementConceptNormalizer;
use Carbon\Carbon;

class ConditionEvaluator
{
    /**
     * Evaluates if a list of conditions match the given context.
     * All conditions must match (Implicit AND).
     *
     * @param RuleConditionDefinition[] $conditions
     */
    public function matches(array $conditions, SettlementContext $context): bool
    {
        if (empty($conditions)) {
            return true;
        }

        foreach ($conditions as $condition) {
            if (!$this->evaluateCondition($condition, $context)) {
                return false;
            }
        }

        return true;
    }

    private function evaluateCondition(RuleConditionDefinition $condition, SettlementContext $context): bool
    {
        $contextValue = $context->getFieldValue($condition->field);
        $expectedValue = $condition->value;

        if ($condition->field === 'concept_key') {
            $contextValue = is_string($contextValue)
                ? SettlementConceptNormalizer::normalize($contextValue)
                : $contextValue;

            if (is_array($expectedValue)) {
                $expectedValue = array_map(function ($value) {
                    return is_string($value)
                        ? SettlementConceptNormalizer::normalize($value)
                        : $value;
                }, $expectedValue);
            } elseif (is_string($expectedValue)) {
                $expectedValue = SettlementConceptNormalizer::normalize($expectedValue);
            }
        }

        switch (strtoupper($condition->operator)) {
            case '=':
            case '==':
                return $this->compareEquality($contextValue, $expectedValue);
            case '!=':
                return ! $this->compareEquality($contextValue, $expectedValue);
            case '>':
                return $this->compareOrder($contextValue, $expectedValue) > 0;
            case '>=':
                return $this->compareOrder($contextValue, $expectedValue) >= 0;
            case '<':
                return $this->compareOrder($contextValue, $expectedValue) < 0;
            case '<=':
                return $this->compareOrder($contextValue, $expectedValue) <= 0;
            case 'IN':
                return is_array($expectedValue) && in_array($contextValue, $expectedValue, true);
            case 'NOT_IN':
                return is_array($expectedValue) && !in_array($contextValue, $expectedValue, true);
            case 'CONTAINS':
                return $this->compareContains($contextValue, $expectedValue);
            default:
                throw new \InvalidArgumentException("Operator [{$condition->operator}] is not supported.");
        }
    }

    private function compareEquality($contextValue, $expectedValue): bool
    {
        [$normalizedContext, $contextType] = $this->normalizeComparable($contextValue);
        [$normalizedExpected, $expectedType] = $this->normalizeComparable($expectedValue);

        if ($contextType === 'date' || $expectedType === 'date') {
            return $normalizedContext === $normalizedExpected;
        }

        return $contextValue == $expectedValue;
    }

    private function compareOrder($contextValue, $expectedValue): int
    {
        [$normalizedContext, $contextType] = $this->normalizeComparable($contextValue);
        [$normalizedExpected, $expectedType] = $this->normalizeComparable($expectedValue);

        if ($contextType === 'date' || $expectedType === 'date') {
            return $normalizedContext <=> $normalizedExpected;
        }

        return (float) $contextValue <=> (float) $expectedValue;
    }

    private function normalizeComparable($value): array
    {
        if ($value instanceof Carbon) {
            return [$value->copy()->startOfDay()->timestamp, 'date'];
        }

        if (is_string($value)) {
            $trimmed = trim($value);

            if ($trimmed !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $trimmed)) {
                try {
                    return [Carbon::parse($trimmed)->startOfDay()->timestamp, 'date'];
                } catch (\Throwable $e) {
                    // fallback to raw string
                }
            }
        }

        return [$value, is_numeric($value) ? 'number' : gettype($value)];
    }

    private function compareContains($contextValue, $expectedValue): bool
    {
        if ($contextValue === null || $expectedValue === null) {
            return false;
        }
        $contextStr = mb_strtolower(trim((string) $contextValue));
        $expectedStr = mb_strtolower(trim((string) $expectedValue));
        
        // Strip wildcards if they are at the ends
        $expectedStr = trim($expectedStr, '%*');
        
        if ($expectedStr === '') {
            return true;
        }
        return str_contains($contextStr, $expectedStr);
    }
}
