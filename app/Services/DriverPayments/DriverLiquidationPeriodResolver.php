<?php

namespace App\Services\DriverPayments;

use App\Models\DriverLiquidationSetting;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class DriverLiquidationPeriodResolver
{
    public function resolveForDate($date, string $tipoPeriodo): array
    {
        $targetDate = $date instanceof Carbon ? $date->copy()->startOfDay() : Carbon::parse($date)->startOfDay();

        $settings = DriverLiquidationSetting::query()
            ->where('tipo_periodo', $tipoPeriodo)
            ->where('activo', true)
            ->get();

        $settings = $settings->filter(function (DriverLiquidationSetting $setting) use ($targetDate) {
            $desdeDia = (int) $setting->desde;
            $hastaDia = (int) $setting->hasta;
            $day = (int) $targetDate->day;

            return $day >= $desdeDia && $day <= $hastaDia;
        });

        if ($settings->isNotEmpty()) {
            return $this->pickBestSetting($settings, $targetDate);
        }

        if ($tipoPeriodo === 'quincenal') {
            $isFirstHalf = (int) $targetDate->day <= 15;
            $desde = $targetDate->copy()->startOfMonth();
            $hasta = $isFirstHalf
                ? $targetDate->copy()->startOfMonth()->day(15)
                : $targetDate->copy()->endOfMonth();

            if (!$isFirstHalf) {
                $desde = $targetDate->copy()->startOfMonth()->day(16);
            }

            return [
                'desde' => $desde,
                'hasta' => $hasta,
                'quincena' => $isFirstHalf ? 1 : 2,
                'setting_id' => null,
            ];
        }

        return [
            'desde' => $targetDate->copy()->startOfMonth(),
            'hasta' => $targetDate->copy()->endOfMonth(),
            'quincena' => null,
            'setting_id' => null,
        ];
    }

    private function pickBestSetting(Collection $settings, Carbon $date): array
    {
        $settings = $settings->sortByDesc(function (DriverLiquidationSetting $setting) use ($date) {
            $score = 0;
            if ($setting->quincena !== null) {
                $score += 1;
            }

            return $score;
        });

        /** @var DriverLiquidationSetting $selected */
        $selected = $settings->first();
        $daysInMonth = $date->copy()->endOfMonth()->day;
        $desdeDia = min((int) $selected->desde, $daysInMonth);
        $hastaDia = min((int) $selected->hasta, $daysInMonth);

        return [
            'desde' => $date->copy()->startOfMonth()->day(max(1, $desdeDia)),
            'hasta' => $date->copy()->startOfMonth()->day(max(1, $hastaDia)),
            'quincena' => $selected->quincena,
            'setting_id' => $selected->id,
        ];
    }
}
