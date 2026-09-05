<?php

namespace App\Http\Controllers;

use App\Models\DriverLiquidationSetting;
use App\Models\ReciboChofer;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class DriverPaymentAgendaController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', ReciboChofer::class);

        $monthInput = trim((string) $request->query('mes', ''));
        $currentMonth = $this->resolveMonth($monthInput);
        $start = $currentMonth->copy()->startOfMonth();
        $end = $currentMonth->copy()->endOfMonth();
        $prevMonth = $currentMonth->copy()->subMonth();

        $settings = DriverLiquidationSetting::query()
            ->where('activo', true)
            ->orderBy('tipo_periodo')
            ->orderBy('quincena')
            ->get();

        $pendingReceipts = ReciboChofer::query()
            ->with('transportista')
            ->whereIn('tipo_periodo', [ReciboChofer::TIPO_QUINCENAL, ReciboChofer::TIPO_MENSUAL])
            ->whereNotIn('estado', [ReciboChofer::ESTADO_PAGADO, ReciboChofer::ESTADO_ANULADO])
            ->get(['id', 'transportista_id', 'tipo_periodo', 'periodo_desde', 'periodo_hasta', 'importe_total', 'estado']);

        $currentQ1Setting = $this->resolveSettingForSlot($settings, $currentMonth, ReciboChofer::TIPO_QUINCENAL, 1);
        $currentQ2Setting = $this->resolveSettingForSlot($settings, $currentMonth, ReciboChofer::TIPO_QUINCENAL, 2);
        $prevQ2Setting = $this->resolveSettingForSlot($settings, $prevMonth, ReciboChofer::TIPO_QUINCENAL, 2);

        $ruleSlots = [
            [
                'tipo' => ReciboChofer::TIPO_QUINCENAL,
                'quincena' => 1,
                'label' => 'Quincena 1',
                'source_start' => $this->resolveWindowStart($prevMonth, $prevQ2Setting),
                'source_end' => $this->resolveWindowStart($currentMonth, $currentQ1Setting),
            ],
            [
                'tipo' => ReciboChofer::TIPO_QUINCENAL,
                'quincena' => 2,
                'label' => 'Quincena 2',
                'source_start' => $this->resolveWindowStart($currentMonth, $currentQ1Setting),
                'source_end' => $this->resolveWindowStart($currentMonth, $currentQ2Setting),
            ],
            [
                'tipo' => ReciboChofer::TIPO_MENSUAL,
                'quincena' => null,
                'label' => 'Mensual',
                'source_start' => $prevMonth->copy()->startOfMonth(),
                'source_end' => $currentMonth->copy()->startOfMonth(),
            ],
        ];

        $eventsByDate = [];
        $monthEvents = [];
        foreach ($ruleSlots as $slot) {
            $setting = $this->resolveSettingForSlot($settings, $currentMonth, $slot['tipo'], $slot['quincena']);
            if (! $setting) {
                continue;
            }

            $fromDay = min(max(1, (int) $setting->desde), $end->day);
            $toDay = min(max(1, (int) $setting->hasta), $end->day);
            $windowStart = $currentMonth->copy()->day(max(1, $fromDay));
            $windowEnd = $currentMonth->copy()->day(max(1, $toDay));

            $receipts = $pendingReceipts->filter(function (ReciboChofer $recibo) use ($slot) {
                return $this->receiptBelongsToSlot($recibo, $slot);
            })->sortBy([
                ['periodo_desde', 'asc'],
                ['id', 'asc'],
            ])->values();

            $eventData = [
                'setting_id' => $setting->id,
                'tipo_periodo' => $setting->tipo_periodo,
                'quincena' => $setting->quincena,
                'label' => $slot['label'],
                'due_date' => $windowStart->copy(),
                'window_start' => $windowStart,
                'window_end' => $windowEnd,
                'receipts_count' => $receipts->count(),
                'receipts_total' => (float) $receipts->sum('importe_total'),
                'scope' => $this->scopeLabel($setting),
                'receipts' => $receipts,
            ];
            $monthEvents[] = $eventData;

            $dateKey = $windowStart->toDateString();
            if (! isset($eventsByDate[$dateKey])) {
                $eventsByDate[$dateKey] = [];
            }
            $eventsByDate[$dateKey][] = $eventData;
        }

        return view('pago-choferes.agenda.index', [
            'currentMonth' => $currentMonth,
            'monthStart' => $start,
            'monthEnd' => $end,
            'calendarWeeks' => $this->buildCalendarWeeks($currentMonth),
            'eventsByDate' => $eventsByDate,
            'monthEvents' => collect($monthEvents),
            'prevMonth' => $currentMonth->copy()->subMonth()->format('Y-m'),
            'nextMonth' => $currentMonth->copy()->addMonth()->format('Y-m'),
        ]);
    }

    private function resolveMonth(string $monthInput): Carbon
    {
        if ($monthInput !== '' && preg_match('/^\d{4}-\d{2}$/', $monthInput)) {
            try {
                return Carbon::createFromFormat('Y-m', $monthInput)->startOfMonth();
            } catch (\Throwable $e) {
                // fallback below
            }
        }

        return now()->startOfMonth();
    }

    private function buildCalendarWeeks(Carbon $month): array
    {
        $first = $month->copy()->startOfMonth()->startOfWeek(Carbon::MONDAY);
        $last = $month->copy()->endOfMonth()->endOfWeek(Carbon::SUNDAY);
        $weeks = [];
        $cursor = $first->copy();

        while ($cursor->lte($last)) {
            $week = [];
            for ($i = 0; $i < 7; $i++) {
                $week[] = $cursor->copy();
                $cursor->addDay();
            }
            $weeks[] = $week;
        }

        return $weeks;
    }

    private function resolveSettingForSlot(Collection $settings, Carbon $month, string $tipo, ?int $quincena): ?DriverLiquidationSetting
    {
        $candidates = $settings->filter(function (DriverLiquidationSetting $setting) use ($tipo, $quincena) {
            if ($setting->tipo_periodo !== $tipo) {
                return false;
            }
            if ((int) ($setting->quincena ?? 0) !== (int) ($quincena ?? 0)) {
                return false;
            }

            return true;
        });

        if ($candidates->isEmpty()) {
            return null;
        }

        return $candidates->sortBy('desde')->first();
    }

    private function scopeLabel(DriverLiquidationSetting $setting): string
    {
        return 'General';
    }

    private function resolveWindowStart(Carbon $month, ?DriverLiquidationSetting $setting): Carbon
    {
        $day = 1;
        if ($setting) {
            $day = max(1, min((int) $setting->desde, $month->copy()->endOfMonth()->day));
        }

        return $month->copy()->day($day)->startOfDay();
    }

    private function receiptBelongsToSlot(ReciboChofer $recibo, array $slot): bool
    {
        if ($recibo->tipo_periodo !== $slot['tipo']) {
            return false;
        }

        $periodStart = $recibo->periodo_desde ? Carbon::parse($recibo->periodo_desde)->startOfDay() : null;
        if (! $periodStart) {
            return false;
        }

        $sourceStart = $slot['source_start'] ?? null;
        $sourceEnd = $slot['source_end'] ?? null;
        if (! $sourceStart || ! $sourceEnd) {
            return false;
        }

        return $periodStart->gte($sourceStart) && $periodStart->lt($sourceEnd);
    }
}
