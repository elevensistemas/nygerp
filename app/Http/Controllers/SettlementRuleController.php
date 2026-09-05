<?php

namespace App\Http\Controllers;

use App\Models\SettlementRule;
use App\Models\SettlementRuleAction;
use App\Models\SettlementRuleCondition;
use App\Services\DriverPayments\RuleEngine\DTOs\SettlementContext;
use App\Services\DriverPayments\RuleEngine\SettlementRuleEngine;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use App\Models\TrafficZone;
use App\Models\Transportista;
use App\Models\DriverPaymentConcept;
use App\Models\DriverPaymentFleet;
use App\Models\DriverPaymentZoneConcept;
use App\Models\DriverImportVehicleMap;
use App\Models\Transporte;

class SettlementRuleController extends Controller
{
    /**
     * Render the Vue application base view.
     */
    public function index()
    {
        return view('pago-choferes.rules.index', [
            'vehicleMaps' => DriverImportVehicleMap::query()->orderBy('excel_value')->get(['id', 'excel_value', 'vehicle_type']),
            'vehicleTypes' => Transporte::paymentVehicleTypes(true),
            'fleets' => DriverPaymentFleet::query()
                ->with(['billingTransportista:id,name', 'transportistas:id,name'])
                ->orderBy('name')
                ->get(['id', 'name', 'billing_transportista_id', 'active']),
            'transportistas' => Transportista::query()
                ->orderBy('name')
                ->get(['id', 'name']),
            'zones' => TrafficZone::query()
                ->orderBy('name')
                ->get(['id', 'name', 'type', 'center_lat', 'center_lng', 'radius_km', 'polygon', 'priority', 'is_soft']),
            'concepts' => DriverPaymentConcept::query()
                ->with('referenceConcept:id,name')
                ->orderBy('name')
                ->get(['id', 'name', 'value_type', 'default_amount', 'sign', 'reference_concept_id', 'reference_multiplier', 'active']),
            'defaultPeriodType' => \App\Models\DriverImportSetting::where('setting_key', 'driver_payment_default_period_type')->first(),
        ]);
    }

    /**
     * Get all active settlement rules with conditions and actions.
     */
    public function getRules()
    {
        $rules = SettlementRule::with(['conditions', 'action'])
            ->orderBy('priority', 'desc')
            ->orderBy('id', 'desc')
            ->get()
            ->map(function ($rule) {
                return [
                    'id' => $rule->id,
                    'name' => $rule->name,
                    'priority' => $rule->priority,
                    'is_modifier' => $rule->is_modifier,
                    'scope' => $rule->scope ?: SettlementRule::SCOPE_LINE,
                    'active' => $rule->active,
                    'action_type' => $rule->action ? $rule->action->action_type : null,
                    'action_payload' => $rule->action ? $rule->action->payload : [],
                    'conditions' => $rule->conditions->map(function ($cond) {
                        return [
                            'field' => $cond->field,
                            'operator' => $cond->operator,
                            'value' => $cond->value,
                        ];
                    }),
                ];
            });

        return response()->json($rules);
    }

    /**
     * Get available configuration options for dropdowns.
     */
    public function getOptions()
    {
        $zones = TrafficZone::orderBy('name')->get(['id', 'name'])->map(function ($z) {
            return ['value' => $z->id, 'label' => $z->name];
        });

        $carriers = Transportista::orderBy('name')->get(['id', 'name'])->map(function ($c) {
            return ['value' => $c->id, 'label' => $c->name];
        });

        $concepts = DriverPaymentConcept::orderBy('name')->get(['id', 'name', 'value_type'])->map(function ($c) {
            return ['value' => $c->name, 'id' => $c->id, 'label' => "{$c->name} ({$c->value_type})"];
        });

        $vehicleTypes = collect(Transporte::paymentVehicleTypes(true))->map(function($label, $key) {
            return ['value' => $key, 'label' => $label];
        })->values()->all();

        return response()->json([
            'zone_id' => $zones,
            'carrier_id' => $carriers,
            'concept_key' => $concepts,
            'vehicle_type' => $vehicleTypes,
        ]);
    }

    /**
     * Create a new rule.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'priority' => 'required|integer',
            'is_modifier' => 'boolean',
            'scope' => 'nullable|string|in:line,receipt',
            'active' => 'boolean',
            'action_type' => 'required|string',
            'action_payload' => 'present|array',
            'conditions' => 'present|array',
            'conditions.*.field' => 'required|string',
            'conditions.*.operator' => 'required|string|in:=,!=,>,<,>=,<=,IN,NOT IN,CONTAINS',
            'conditions.*.value' => 'present',
        ]);

        DB::beginTransaction();

        try {
            $rule = SettlementRule::create([
                'name' => $data['name'],
                'priority' => $data['priority'],
                'is_modifier' => $data['is_modifier'] ?? false,
                'scope' => $data['scope'] ?? SettlementRule::SCOPE_LINE,
                'active' => $data['active'] ?? true,
            ]);

            foreach ($data['conditions'] as $condData) {
                SettlementRuleCondition::create([
                    'settlement_rule_id' => $rule->id,
                    'field' => $condData['field'],
                    'operator' => $condData['operator'],
                    'value' => is_array($condData['value']) ? json_encode($condData['value']) : (string)$condData['value'],
                ]);
            }

            SettlementRuleAction::create([
                'settlement_rule_id' => $rule->id,
                'action_type' => $data['action_type'],
                'payload' => $this->normalizeActionPayload($data['action_type'], $data['action_payload'] ?? []),
            ]);

            DB::commit();
            \App\Services\DriverPayments\RuleEngine\Repositories\CachedEloquentSettlementRuleRepository::flushCache();

            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Update an existing rule (transactional replacement of children).
     */
    public function update(Request $request, $id)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'priority' => 'required|integer',
            'is_modifier' => 'boolean',
            'scope' => 'nullable|string|in:line,receipt',
            'active' => 'boolean',
            'action_type' => 'required|string',
            'action_payload' => 'present|array',
            'conditions' => 'present|array',
            'conditions.*.field' => 'required|string',
            'conditions.*.operator' => 'required|string|in:=,!=,>,<,>=,<=,IN,NOT IN,CONTAINS',
            'conditions.*.value' => 'present',
        ]);

        DB::beginTransaction();

        try {
            $rule = SettlementRule::findOrFail($id);
            $rule->update([
                'name' => $data['name'],
                'priority' => $data['priority'],
                'is_modifier' => $data['is_modifier'] ?? false,
                'scope' => $data['scope'] ?? SettlementRule::SCOPE_LINE,
                'active' => $data['active'] ?? true,
            ]);

            $rule->conditions()->forceDelete();
            foreach ($data['conditions'] as $condData) {
                SettlementRuleCondition::create([
                    'settlement_rule_id' => $rule->id,
                    'field' => $condData['field'],
                    'operator' => $condData['operator'],
                    'value' => is_array($condData['value']) ? json_encode($condData['value']) : (string)$condData['value'],
                ]);
            }

            $rule->action()->forceDelete();
            SettlementRuleAction::create([
                'settlement_rule_id' => $rule->id,
                'action_type' => $data['action_type'],
                'payload' => $this->normalizeActionPayload($data['action_type'], $data['action_payload'] ?? []),
            ]);

            DB::commit();
            \App\Services\DriverPayments\RuleEngine\Repositories\CachedEloquentSettlementRuleRepository::flushCache();

            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Delete a rule (logical).
     */
    public function destroy($id)
    {
        $rule = SettlementRule::findOrFail($id);
        $rule->delete();
        \App\Services\DriverPayments\RuleEngine\Repositories\CachedEloquentSettlementRuleRepository::flushCache();

        return response()->json(['success' => true]);
    }

    /**
     * Toggle active state.
     */
    public function toggle($id)
    {
        $rule = SettlementRule::findOrFail($id);
        $rule->update(['active' => !$rule->active]);
        \App\Services\DriverPayments\RuleEngine\Repositories\CachedEloquentSettlementRuleRepository::flushCache();

        return response()->json(['success' => true, 'active' => $rule->active]);
    }

    /**
     * Simulate execution of a specific rule or the full engine.
     */
    public function simulate(Request $request, SettlementRuleEngine $engine)
    {
        $data = $request->validate([
            'simulate_mode' => 'required|in:single_rule,full_engine',
            'rule_data' => 'nullable|array', // Only needed for single_rule if not saved, or we can just run against an ID. Wait, we want to simulate from UI without saving!
            'context' => 'required|array',
            'context.trip_date' => 'required|date',
            'context.carrier_id' => 'required|integer',
            'context.concept_name' => 'nullable|string',
            'context.concept_key' => 'nullable|string',
            'context.zone_id' => 'nullable|integer',
            'context.vehicle_type' => 'required|string',
            'context.model_year' => 'nullable|integer',
            'context.receipt_date' => 'nullable|date',
            'context.period_start' => 'nullable|date',
            'context.period_end' => 'nullable|date',
            'context.kilometers' => 'required|numeric',
            'context.delivered_packages' => 'required|numeric',
            'context.absent_packages' => 'required|numeric',
            'context.stops' => 'required|numeric',
            'context.is_remote_zone' => 'required|boolean',
        ]);

        $ctxData = $data['context'];
        $context = new SettlementContext(
            Carbon::parse($ctxData['trip_date']),
            (int) $ctxData['carrier_id'],
            $ctxData['concept_name'] ?? null,
            $ctxData['zone_id'] ?? null,
            $ctxData['vehicle_type'],
            $ctxData['model_year'] ?? null,
            (float) $ctxData['kilometers'],
            (float) $ctxData['delivered_packages'],
            (float) $ctxData['absent_packages'],
            (float) $ctxData['stops'],
            (bool) $ctxData['is_remote_zone'],
            ! empty($ctxData['receipt_date']) ? Carbon::parse($ctxData['receipt_date']) : null,
            ! empty($ctxData['period_start']) ? Carbon::parse($ctxData['period_start']) : null,
            ! empty($ctxData['period_end']) ? Carbon::parse($ctxData['period_end']) : null
        );

        if (!empty($ctxData['concept_key'])) {
            $context->concept_key = $ctxData['concept_key'];
        }

        if ($data['simulate_mode'] === 'single_rule') {
            // Evaluates purely one Rule definition built from request memory
            // This allows testing before saving
            $ruleDefData = $data['rule_data'] ?? null;
            if (!$ruleDefData) {
                return response()->json(['error' => 'Rule data is required for single rule simulation'], 400);
            }

            // Convert raw request into RuleDefinition DTO
            $conditions = [];
            foreach ($ruleDefData['conditions'] ?? [] as $cond) {
                $conditions[] = new \App\Services\DriverPayments\RuleEngine\DTOs\RuleConditionDefinition(
                    $cond['field'],
                    $cond['operator'],
                    $cond['value']
                );
            }

            $ruleDef = new \App\Services\DriverPayments\RuleEngine\DTOs\RuleDefinition(
                0, // ID 0 means temporary
                $ruleDefData['name'] ?? 'Simulation',
                $ruleDefData['priority'] ?? 0,
                $ruleDefData['is_modifier'] ?? false,
                $ruleDefData['scope'] ?? SettlementRule::SCOPE_LINE,
                $ruleDefData['action_type'],
                $ruleDefData['action_payload'] ?? [],
                $conditions
            );

            // We evaluate the conditions standalone using the global evaluator
            $evaluator = app(\App\Services\DriverPayments\RuleEngine\Evaluators\ConditionEvaluator::class);
            $matches = $evaluator->matches($ruleDef->conditions, $context);
            
            if (!$matches) {
                // Let's dump the context values vs expected to debug!
                $failures = [];
                foreach ($ruleDef->conditions as $cond) {
                    $cVal = $context->getFieldValue($cond->field);
                    // simple check
                    $op = $cond->operator;
                    $expected = $cond->value;
                    $success = false;
                    switch (strtoupper($op)) {
                        case '=': case '==': $success = ($cVal == $expected); break;
                        case '!=': $success = ($cVal != $expected); break;
                        case '>': $success = ((float)$cVal > (float)$expected); break;
                        case '<': $success = ((float)$cVal < (float)$expected); break;
                    }
                    if (!$success) {
                        $failures[] = "Field {$cond->field}: expected {$op} {$expected}, but got {$cVal}";
                    }
                }
                return response()->json([
                    'matched' => false,
                    'message' => 'The provided context does not match the rule conditions. Failures: ' . implode(', ', $failures),
                    'breakdown' => null
                ]);
            }

            // Run action handler directly!
            $handler = \App\Services\DriverPayments\RuleEngine\Actions\ActionHandlerFactory::resolve($ruleDef->actionType);
            // Use the real $engine injected in the controller method so COMPOSITE rule has access to database sub-rules
            
            try {
                $result = $handler->execute($ruleDef->actionPayload, $context, $engine);
                return response()->json([
                    'matched' => true,
                    'calculated_amount' => $result->calculatedAmount,
                    'trace' => $result->actionTrace,
                ]);
            } catch (\Exception $e) {
                return response()->json(['error' => $e->getMessage()], 422);
            }
        }

        // Full Engine Mode
        try {
            $breakdown = $engine->calculate($context);
            return response()->json([
                'matched' => true,
                'total_amount' => $breakdown->totalAmount,
                'base_amount' => $breakdown->baseAmount,
                'breakdown' => $breakdown->toArray(),
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    private function normalizeActionPayload(string $actionType, array $payload): array
    {
        $type = strtoupper(trim($actionType));

        switch ($type) {
            case 'MULTIPLIER':
                return array_filter([
                'target_field' => $payload['target_field'] ?? $payload['multiply_by_field'] ?? 'kilometers',
                'rate' => (float) ($payload['rate'] ?? $payload['multiplier_rate'] ?? $payload['multiplier'] ?? 0),
                'field_offset' => (float) ($payload['field_offset'] ?? $payload['subtract_value'] ?? 0),
                'receipt_concept' => $payload['receipt_concept'] ?? null,
                ], static fn ($value) => $value !== null);
            case 'BASE_PLUS_MULTIPLIER':
                return array_filter([
                'base_amount' => (float) ($payload['base_amount'] ?? 0),
                'target_field' => $payload['target_field'] ?? $payload['multiply_by_field'] ?? 'kilometers',
                'rate' => (float) ($payload['rate'] ?? $payload['multiplier_rate'] ?? $payload['multiplier'] ?? 0),
                'field_offset' => (float) ($payload['field_offset'] ?? $payload['subtract_value'] ?? 0),
                'receipt_concept' => $payload['receipt_concept'] ?? null,
                ], static fn ($value) => $value !== null);
            case 'THRESHOLD_EXCESS':
                return array_filter([
                'target_field' => $payload['target_field'] ?? $payload['evaluate_field'] ?? 'total_packages',
                'threshold_qty' => (float) ($payload['threshold_qty'] ?? $payload['threshold_limit'] ?? 0),
                'base_amount' => (float) ($payload['base_amount'] ?? 0),
                'excess_rate' => (float) ($payload['excess_rate'] ?? $payload['excess_multiplier_rate'] ?? 0),
                'receipt_concept' => $payload['receipt_concept'] ?? null,
                ], static fn ($value) => $value !== null);
            default:
                if (! empty($payload['receipt_concept'])) {
                    $payload['receipt_concept'] = $payload['receipt_concept'];
                }
                return $payload;
        }
    }
}
