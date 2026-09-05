<?php

namespace App\Services\DriverPayments;

use App\Models\DriverPaymentAdjustmentRule;
use App\Models\DriverPaymentConcept;
use App\Models\DriverImportVehicleMap;
use App\Models\ReciboChofer;
use App\Models\ReciboChoferItem;
use App\Models\User;
use App\Services\DriverPayments\RuleEngine\DTOs\SettlementContext;
use App\Services\DriverPayments\RuleEngine\SettlementRuleEngine;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ReceiptRecalculator
{
    private SettlementRuleEngine $ruleEngine;
    private ReceiptScopedRuleSynchronizer $receiptScopedRuleSynchronizer;
    private ?array $vehicleMapsCache = null;

    public function __construct(
        SettlementRuleEngine $ruleEngine,
        ReceiptScopedRuleSynchronizer $receiptScopedRuleSynchronizer
    ) {
        $this->ruleEngine = $ruleEngine;
        $this->receiptScopedRuleSynchronizer = $receiptScopedRuleSynchronizer;
    }

    public function recalculate(ReciboChofer $recibo, ?User $user = null): int
    {
        return DB::transaction(function () use ($recibo, $user) {
            $recibo->loadMissing('items');

            $recalculated = 0;

            foreach ($recibo->items as $item) {
                if (! $this->shouldRecalculateItem($item)) {
                    continue;
                }

                $meta = is_array($item->meta) ? $item->meta : [];
                $context = $this->buildContext($recibo, $item, $meta);
                $breakdown = $this->ruleEngine->calculate($context);

                [$signedUnitAmount, $signedTotalAmount] = $this->applyConceptSign((string) $item->concepto, (float) $breakdown->baseAmount, (float) $breakdown->totalAmount);
                $item->importe_unitario = $signedUnitAmount;
                $item->importe = $signedTotalAmount;
                $item->meta = array_merge($meta, [
                    'vehicle_type' => $context->vehicle_type,
                    'pricing_breakdown' => $breakdown->toArray(),
                ]);
                $item->save();

                $recalculated++;
            }

            $this->receiptScopedRuleSynchronizer->sync($recibo);
            $this->syncAutomaticAdjustmentsForReceipt($recibo);
            $this->syncAdvanceRequestsForReceipt($recibo);

            if ($user) {
                $recibo->updated_by = $user->id;
            }

            $recibo->recalculateTotal();

            return $recalculated;
        });
    }

    private function shouldRecalculateItem(ReciboChoferItem $item): bool
    {
        $meta = is_array($item->meta) ? $item->meta : [];

        if (($meta['manual'] ?? false) === true) {
            return false;
        }

        if (($meta['auto_adjustment'] ?? false) === true) {
            return false;
        }

        if (($meta['receipt_rule'] ?? false) === true) {
            return false;
        }

        return ! empty($item->source_key);
    }

    private function buildContext(ReciboChofer $recibo, ReciboChoferItem $item, array $meta): SettlementContext
    {
        $date = $this->resolveItemDate($meta, $recibo);
        $conceptName = trim((string) ($item->concepto ?? $meta['zona'] ?? ''));
        $zoneId = isset($meta['traffic_zone_id']) && $meta['traffic_zone_id'] !== ''
            ? (int) $meta['traffic_zone_id']
            : null;
        $vehicleType = $this->resolveVehicleTypeFromMeta($meta);
        $modelYear = isset($meta['model_year']) && $meta['model_year'] !== ''
            ? (int) $meta['model_year']
            : null;
        $kilometers = $this->toFloat($meta['kilometros'] ?? $meta['kilometros_estimados'] ?? 0);
        $deliveredPackages = $this->toFloat($item->entregados ?? $meta['entregados'] ?? $item->paquetes ?? $meta['paquetes'] ?? 0);
        $absentPackages = $this->toFloat($meta['paquetes_ausentes'] ?? 0);
        $stops = $this->toFloat($item->paradas ?? $meta['paradas'] ?? 0);
        $isRemoteZone = (bool) ($meta['zona_lejana'] ?? false);

        return new SettlementContext(
            $date,
            (int) ($meta['source_transportista_id'] ?? $recibo->transportista_id),
            $conceptName !== '' ? $conceptName : null,
            $zoneId,
            $vehicleType !== '' ? $vehicleType : 'general',
            $modelYear,
            $kilometers,
            $deliveredPackages,
            $absentPackages,
            $stops,
            $isRemoteZone
        );
    }

    private function resolveItemDate(array $meta, ReciboChofer $recibo): Carbon
    {
        $dateValue = $meta['fecha'] ?? null;
        if (is_string($dateValue) && trim($dateValue) !== '') {
            try {
                return Carbon::parse($dateValue);
            } catch (\Throwable $e) {
                // fall through
            }
        }

        if ($recibo->periodo_desde) {
            return $recibo->periodo_desde->copy();
        }

        return now();
    }

    private function syncAutomaticAdjustmentsForReceipt(ReciboChofer $recibo): void
    {
        if (! Schema::hasTable('driver_payment_adjustment_rules')) {
            return;
        }

        $recibo->items()->where('source_key', 'like', 'auto-adjustment:%')->delete();
        $items = $recibo->items()->get();
        if ($items->isEmpty()) {
            return;
        }

        $rules = DriverPaymentAdjustmentRule::query()->where('active', true)->get();
        foreach ($rules as $rule) {
            $matchingItems = $items->filter(function ($item) use ($rule) {
                $meta = is_array($item->meta) ? $item->meta : [];

                $itemCarrierId = (int) ($meta['source_transportista_id'] ?? $meta['transportista_id'] ?? $item->recibo->transportista_id);
                if ($rule->transportista_id !== null && $itemCarrierId !== (int) $rule->transportista_id) {
                    return false;
                }

                if (($meta['manual'] ?? false) === true || ($meta['auto_adjustment'] ?? false) === true) {
                    return false;
                }

                if (($meta['receipt_rule'] ?? false) === true) {
                    return false;
                }

                if ($rule->traffic_zone_id !== null && (int) ($meta['traffic_zone_id'] ?? 0) !== (int) $rule->traffic_zone_id) {
                    return false;
                }

                if (($rule->vehicle_type ?? 'general') !== 'general' && ($meta['vehicle_type'] ?? 'general') !== $rule->vehicle_type) {
                    return false;
                }

                return true;
            });

            if ($matchingItems->isEmpty()) {
                continue;
            }

            $signedAmount = round((float) $rule->amount * ((int) $rule->sign === -1 ? -1 : 1), 2);
            ReciboChoferItem::create([
                'recibo_chofer_id' => $recibo->id,
                'concepto' => $rule->name,
                'cantidad' => 1,
                'importe_unitario' => $signedAmount,
                'importe' => $signedAmount,
                'source_key' => 'auto-adjustment:' . $recibo->id . ':' . $rule->id,
                'meta' => [
                    'auto_adjustment' => true,
                    'driver_payment_adjustment_rule_id' => $rule->id,
                    'traffic_zone_id' => $rule->traffic_zone_id,
                    'vehicle_type' => $rule->vehicle_type,
                    'transportista_id' => $rule->transportista_id,
                    'driver_payment_fleet_id' => $recibo->driver_payment_fleet_id,
                ],
            ]);
        }
    }

    private function applyConceptSign(string $conceptName, float $unitAmount, float $totalAmount): array
    {
        $concept = DriverPaymentConcept::query()->whereRaw('LOWER(name) = ?', [mb_strtolower(trim($conceptName))])->first();
        if (! $concept || (int) $concept->sign !== -1) {
            return [$unitAmount, $totalAmount];
        }

        return [abs($unitAmount) * -1, abs($totalAmount) * -1];
    }

    private function resolveVehicleTypeFromMeta(array $meta): string
    {
        $current = trim((string) ($meta['vehicle_type'] ?? 'general'));
        if ($current !== '' && $current !== 'general') {
            return $current;
        }

        foreach ([$meta['unidad'] ?? null, $meta['modelo'] ?? null, $meta['observacion'] ?? null] as $candidate) {
            $normalizedCandidate = Str::lower(Str::ascii(trim((string) $candidate)));
            if ($normalizedCandidate === '') {
                continue;
            }

            $maps = $this->vehicleMapsCache();
            if (isset($maps[$normalizedCandidate])) {
                return $maps[$normalizedCandidate];
            }

            if (str_contains($normalizedCandidate, 'camioneta grande')) {
                return 'camioneta_grande';
            }
            if (str_contains($normalizedCandidate, 'camioneta mediana') || str_contains($normalizedCandidate, 'ut mediano')) {
                return 'camioneta_mediana';
            }
            if (str_contains($normalizedCandidate, 'camioneta')) {
                return 'camioneta';
            }
            if (str_contains($normalizedCandidate, 'moto')) {
                return 'moto';
            }
        }

        return 'general';
    }

    private function vehicleMapsCache(): array
    {
        if ($this->vehicleMapsCache !== null) {
            return $this->vehicleMapsCache;
        }

        return $this->vehicleMapsCache = DriverImportVehicleMap::query()
            ->get()
            ->mapWithKeys(function (DriverImportVehicleMap $map) {
                return [Str::lower(Str::ascii(trim((string) $map->excel_value))) => $map->vehicle_type];
            })
            ->all();
    }

    private function toFloat($value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }

        return (float) $value;
    }

    private function syncAdvanceRequestsForReceipt(ReciboChofer $recibo): void
    {
        if (! Schema::hasTable('driver_advance_requests')) {
            return;
        }

        // 1. Revert previously linked advance requests for this receipt
        \App\Models\DriverAdvanceRequest::query()
            ->where('recibo_chofer_id', $recibo->id)
            ->update(['recibo_chofer_id' => null]);

        // 2. Delete existing items for advance requests
        $recibo->items()->where('source_key', 'like', 'advance-request:%')->delete();

        // 3. Find all approved advance requests for this carrier that have no receipt assigned
        $pendingRequests = \App\Models\DriverAdvanceRequest::query()
            ->where('transportista_id', $recibo->transportista_id)
            ->where('estado', \App\Models\DriverAdvanceRequest::ESTADO_APROBADO)
            ->whereNull('recibo_chofer_id')
            ->get();

        foreach ($pendingRequests as $req) {
            $amount = -abs((float) $req->monto_aprobado);
            ReciboChoferItem::create([
                'recibo_chofer_id' => $recibo->id,
                'concepto' => 'Descuento Adelanto (Solicitud #' . $req->id . ')',
                'cantidad' => 1,
                'importe_unitario' => $amount,
                'importe' => $amount,
                'source_key' => 'advance-request:' . $req->id,
                'meta' => [
                    'manual' => true,
                    'driver_advance_request_id' => $req->id,
                ],
            ]);

            $req->update(['recibo_chofer_id' => $recibo->id]);
        }
    }
}
