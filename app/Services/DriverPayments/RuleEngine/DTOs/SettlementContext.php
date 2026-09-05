<?php

namespace App\Services\DriverPayments\RuleEngine\DTOs;

use Carbon\Carbon;
use App\Services\DriverPayments\RuleEngine\Formatting\SettlementConceptNormalizer;

class SettlementContext
{
    public Carbon $trip_date;
    public int $carrier_id;
    public ?string $concept_name;
    public ?string $concept_key;
    public ?int $zone_id;
    public string $vehicle_type;
    public ?int $model_year;
    public float $kilometers;
    public float $delivered_packages;
    public float $absent_packages;
    public float $stops;
    public bool $is_remote_zone;
    public float $total_packages;
    public ?Carbon $receipt_date;
    public ?Carbon $period_start;
    public ?Carbon $period_end;
    public ?string $carrier_name = null;

    public function __construct(
        Carbon $trip_date,
        int $carrier_id,
        ?string $concept_name,
        ?int $zone_id,
        string $vehicle_type,
        ?int $model_year,
        float $kilometers,
        float $delivered_packages,
        float $absent_packages,
        float $stops,
        bool $is_remote_zone,
        ?Carbon $receipt_date = null,
        ?Carbon $period_start = null,
        ?Carbon $period_end = null
    ) {
        $this->trip_date = $trip_date;
        $this->carrier_id = $carrier_id;
        $this->concept_name = $concept_name;
        $this->concept_key = $concept_name !== null ? SettlementConceptNormalizer::normalize($concept_name) : null;
        $this->zone_id = $zone_id;
        $this->vehicle_type = $vehicle_type;
        $this->model_year = $model_year;
        $this->kilometers = $kilometers;
        $this->delivered_packages = $delivered_packages;
        $this->absent_packages = $absent_packages;
        $this->stops = $stops;
        $this->is_remote_zone = $is_remote_zone;
        $this->total_packages = $this->delivered_packages + $this->absent_packages;
        $this->receipt_date = $receipt_date ? $receipt_date->copy() : null;
        $this->period_start = $period_start ? $period_start->copy() : null;
        $this->period_end = $period_end ? $period_end->copy() : null;
    }

    /**
     * Get the exact normalized snake_case field value for condition evaluation.
     */
    public function getFieldValue(string $field)
    {
        if ($field === 'carrier_name') {
            return $this->carrier_name ?? $this->resolveCarrierName();
        }

        $map = [
            'trip_date' => $this->trip_date,
            'carrier_id' => $this->carrier_id,
            'concept_name' => $this->concept_name,
            'concept_key' => $this->concept_key,
            'zone_id' => $this->zone_id,
            'vehicle_type' => $this->vehicle_type,
            'model_year' => $this->model_year,
            'kilometers' => $this->kilometers,
            'delivered_packages' => $this->delivered_packages,
            'absent_packages' => $this->absent_packages,
            'stops' => $this->stops,
            'is_remote_zone' => $this->is_remote_zone,
            'total_packages' => $this->total_packages,
            'receipt_date' => $this->receipt_date,
            'period_start' => $this->period_start,
            'period_end' => $this->period_end,
        ];

        if (!array_key_exists($field, $map)) {
            throw new \InvalidArgumentException("Context field [{$field}] is not defined.");
        }

        return $map[$field];
    }

    public function withConceptName(?string $conceptName): self
    {
        $clone = new self(
            $this->trip_date->copy(),
            $this->carrier_id,
            $conceptName,
            $this->zone_id,
            $this->vehicle_type,
            $this->model_year,
            $this->kilometers,
            $this->delivered_packages,
            $this->absent_packages,
            $this->stops,
            $this->is_remote_zone,
            $this->receipt_date,
            $this->period_start,
            $this->period_end
        );
        $clone->carrier_name = $this->carrier_name;
        return $clone;
    }

    private function resolveCarrierName(): ?string
    {
        static $cache = [];
        if (!isset($cache[$this->carrier_id])) {
            $carrier = \App\Models\Transportista::find($this->carrier_id);
            $cache[$this->carrier_id] = $carrier ? $carrier->name : null;
        }
        return $cache[$this->carrier_id];
    }
}
