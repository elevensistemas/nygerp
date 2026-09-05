<?php

namespace App\Services\DriverPayments\RuleEngine\Repositories;

use App\Models\SettlementRule;
use App\Services\DriverPayments\RuleEngine\Contracts\SettlementRuleRepositoryInterface;
use App\Services\DriverPayments\RuleEngine\DTOs\RuleConditionDefinition;
use App\Services\DriverPayments\RuleEngine\DTOs\RuleDefinition;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class CachedEloquentSettlementRuleRepository implements SettlementRuleRepositoryInterface
{
    private const CACHE_KEY = 'settlement_rule_engine_rules';
    private const CACHE_TTL_SECONDS = 3600; // 1 hour

    /**
     * @return RuleDefinition[]
     */
    public function getActiveRules(?string $scope = null): array
    {
        $normalizedScope = $scope !== null ? trim(strtolower($scope)) : 'all';
        $cacheKey = self::CACHE_KEY . ':' . $normalizedScope;

        return Cache::remember($cacheKey, self::CACHE_TTL_SECONDS, function () use ($scope) {
            $query = SettlementRule::with(['conditions', 'action'])->active();

            if (Schema::hasColumn('settlement_rules', 'scope')) {
                if ($scope !== null) {
                    $query->where('scope', $scope);
                }
            } elseif ($scope !== null && $scope !== SettlementRule::SCOPE_LINE) {
                return [];
            }

            $rules = $query->get();

            return $rules->map(function (SettlementRule $rule) {
                $conditions = $rule->conditions->map(function ($condition) {
                    return new RuleConditionDefinition(
                        $condition->field,
                        $condition->operator,
                        $condition->value
                    );
                })->all();

                $actionType = $rule->action ? $rule->action->action_type : 'NO_PAYMENT';
                $actionPayload = $rule->action ? $rule->action->payload : [];

                return new RuleDefinition(
                    $rule->id,
                    $rule->name,
                    $rule->priority,
                    $rule->is_modifier,
                    Schema::hasColumn('settlement_rules', 'scope') ? (string) ($rule->scope ?: SettlementRule::SCOPE_LINE) : SettlementRule::SCOPE_LINE,
                    $actionType,
                    is_array($actionPayload) ? $actionPayload : [],
                    $conditions
                );
            })->all();
        });
    }

    /**
     * Call this when rules change in the DB
     */
    public static function flushCache(): void
    {
        foreach (['all', SettlementRule::SCOPE_LINE, SettlementRule::SCOPE_RECEIPT] as $scopeKey) {
            Cache::forget(self::CACHE_KEY . ':' . $scopeKey);
        }
    }
}
