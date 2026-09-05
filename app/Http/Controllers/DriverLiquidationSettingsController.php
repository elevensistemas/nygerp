<?php

namespace App\Http\Controllers;

use App\Http\Requests\DriverLiquidationSettingRequest;
use App\Models\DriverImportSetting;
use App\Models\DriverPaymentAdjustmentRule;
use App\Models\DriverPaymentFleet;
use App\Models\DriverPaymentKmRange;
use App\Models\DriverLiquidationSetting;
use App\Models\DriverPaymentConcept;
use App\Models\DriverPaymentType;
use App\Models\DriverPaymentZoneConceptYearValue;
use App\Models\DriverPaymentZoneConcept;
use App\Models\DriverPaymentZoneSetting;
use App\Models\DriverImportVehicleMap;
use App\Models\TrafficZone;
use App\Models\Transportista;
use App\Models\Transporte;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DriverLiquidationSettingsController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', DriverLiquidationSetting::class);

        return view('pago-choferes.settings.index', [
            'settings' => DriverLiquidationSetting::query()->orderBy('tipo_periodo')->orderBy('quincena')->orderBy('desde')->get(),
            'columnMap' => DriverImportSetting::where('setting_key', 'excel_column_map')->first(),
            'defaultPeriodType' => DriverImportSetting::where('setting_key', 'driver_payment_default_period_type')->first(),
            'logisticsImportMode' => DriverImportSetting::where('setting_key', 'driver_payment_logistics_import_mode')->first(),
            'newDriverDays' => DriverImportSetting::where('setting_key', 'driver_new_days')->first(),
            'newDriverColor' => DriverImportSetting::where('setting_key', 'driver_new_color')->first(),
            'zones' => TrafficZone::query()->orderBy('name')->get(),
            'concepts' => DriverPaymentConcept::query()->with('referenceConcept:id,name')->orderBy('name')->get(),
            'fleets' => DriverPaymentFleet::query()
                ->with(['billingTransportista:id,name', 'transportistas:id,name'])
                ->orderBy('name')
                ->get(),
            'transportistas' => Transportista::query()->orderBy('name')->get(['id', 'name']),
            'vehicleTypes' => Transporte::paymentVehicleTypes(true),
            'paymentTypes' => DriverPaymentType::query()->orderByDesc('type')->orderBy('description')->get(),
            'zoneConcepts' => DriverPaymentZoneConcept::query()
                ->with(['trafficZone', 'concept', 'referenceConcept'])
                ->orderBy('traffic_zone_id')
                ->orderBy('vehicle_type')
                ->orderBy('driver_payment_concept_id')
                ->get(),
            'zoneConceptYearValues' => DriverPaymentZoneConceptYearValue::query()
                ->orderBy('traffic_zone_id')
                ->orderBy('vehicle_type')
                ->orderBy('driver_payment_concept_id')
                ->orderBy('year_from')
                ->orderBy('year_to')
                ->get(),
            'adjustmentRules' => DriverPaymentAdjustmentRule::query()
                ->with(['zone:id,name', 'transportista:id,name'])
                ->orderBy('name')
                ->get(),
            'zoneSettings' => DriverPaymentZoneSetting::query()->get()->keyBy('traffic_zone_id'),
            'packageRateDefault' => $this->resolvePackageRateDefault(),
            'kmRanges' => DriverPaymentKmRange::query()
                ->with('zone:id,name')
                ->orderByRaw('traffic_zone_id is not null')
                ->orderBy('traffic_zone_id')
                ->orderBy('vehicle_type')
                ->orderBy('km_from')
                ->get(),
            'vehicleMaps' => DriverImportVehicleMap::query()->orderBy('excel_value')->get(),
        ]);
    }

    public function store(DriverLiquidationSettingRequest $request): RedirectResponse
    {
        $this->authorize('create', DriverLiquidationSetting::class);

        $data = $request->validated();
        $this->abortIfOverlapping(null, $data);

        DriverLiquidationSetting::create([
            'tipo_periodo' => $data['tipo_periodo'],
            'quincena' => $data['quincena'] ?? null,
            'desde' => $this->normalizeDay((int) $data['desde']),
            'hasta' => $this->normalizeDay((int) $data['hasta']),
            'activo' => (bool) ($data['activo'] ?? true),
        ]);

        $this->saveColumnMap($request);

        return redirect()->route('pago-choferes.settings.index')->with('ok', 'Configuracion guardada.');
    }

    public function update(DriverLiquidationSettingRequest $request, DriverLiquidationSetting $setting): RedirectResponse
    {
        $this->authorize('update', $setting);

        $data = $request->validated();
        $this->abortIfOverlapping($setting->id, $data);

        $setting->update([
            'tipo_periodo' => $data['tipo_periodo'],
            'quincena' => $data['quincena'] ?? null,
            'desde' => $this->normalizeDay((int) $data['desde']),
            'hasta' => $this->normalizeDay((int) $data['hasta']),
            'activo' => (bool) ($data['activo'] ?? true),
        ]);

        $this->saveColumnMap($request);

        return redirect()->route('pago-choferes.settings.index')->with('ok', 'Configuracion actualizada.');
    }

    public function destroy(DriverLiquidationSetting $setting): RedirectResponse
    {
        $this->authorize('delete', $setting);

        $setting->delete();

        return redirect()->route('pago-choferes.settings.index')->with('ok', 'Configuracion eliminada.');
    }

    public function storeZone(Request $request): RedirectResponse
    {
        $this->authorize('viewAny', DriverLiquidationSetting::class);

        $data = $request->validate([
            'zone_name' => ['required', 'string', 'max:255'],
            'zone_calc_type' => ['nullable', Rule::in(['ZONA', 'KM', 'PAQUETE'])],
        ]);

        $name = trim((string) $data['zone_name']);
        if ($name === '') {
            abort(422, 'El nombre de zona es obligatorio.');
        }

        TrafficZone::firstOrCreate(
            ['name' => $name],
            [
                'type' => 'circle',
                'priority' => 'secondary',
                'is_soft' => true,
                'center_lat' => null,
                'center_lng' => null,
                'radius_km' => null,
                'polygon' => null,
            ]
        );

        $zone = TrafficZone::where('name', $name)->first();
        if ($zone) {
            DriverPaymentZoneSetting::updateOrCreate(
                ['traffic_zone_id' => $zone->id],
                ['calc_type' => $data['zone_calc_type'] ?? DriverPaymentZoneSetting::TYPE_ZONA]
            );
        }

        return redirect()->route('pago-choferes.settings.index')->with('ok', 'Zona disponible para liquidacion.');
    }

    public function updateZoneType(Request $request, TrafficZone $zone): RedirectResponse
    {
        $this->authorize('viewAny', DriverLiquidationSetting::class);

        $data = $request->validate([
            'calc_type' => ['required', Rule::in(['ZONA', 'KM', 'PAQUETE'])],
            'uses_model_year_values' => ['nullable', 'boolean'],
            'package_rate' => ['nullable', 'numeric', 'min:0'],
            'km_package_threshold' => ['nullable', 'integer', 'min:0'],
            'km_excess_package_amount' => ['nullable', 'numeric', 'min:0'],
            'km_remote_zone_plus_large' => ['nullable', 'numeric', 'min:0'],
            'package_delivered_rate' => ['nullable', 'numeric', 'min:0'],
            'package_absent_rate_multiplier' => ['nullable', 'numeric', 'min:0'],
            'package_fixed_amount' => ['nullable', 'numeric', 'min:0'],
            'package_excess_threshold' => ['nullable', 'integer', 'min:0'],
            'package_excess_amount' => ['nullable', 'numeric', 'min:0'],
        ]);

        DriverPaymentZoneSetting::updateOrCreate(
            ['traffic_zone_id' => $zone->id],
            [
                'calc_type' => $data['calc_type'],
                'package_rate' => $this->nullableDecimal($data['package_rate'] ?? null),
                'km_package_threshold' => $this->nullableInt($data['km_package_threshold'] ?? null),
                'km_excess_package_amount' => $this->nullableDecimal($data['km_excess_package_amount'] ?? null),
                'km_remote_zone_plus_large' => $this->nullableDecimal($data['km_remote_zone_plus_large'] ?? null),
                'package_delivered_rate' => $this->nullableDecimal($data['package_delivered_rate'] ?? null),
                'package_absent_rate_multiplier' => $this->nullableDecimal($data['package_absent_rate_multiplier'] ?? null),
                'package_fixed_amount' => $this->nullableDecimal($data['package_fixed_amount'] ?? null),
                'package_excess_threshold' => $this->nullableInt($data['package_excess_threshold'] ?? null),
                'package_excess_amount' => $this->nullableDecimal($data['package_excess_amount'] ?? null),
            ]
        );

        $zone->update([
            'uses_model_year_values' => array_key_exists('uses_model_year_values', $data)
                ? (bool) $data['uses_model_year_values']
                : false,
        ]);

        return redirect()->route('pago-choferes.settings.index')->with('ok', 'Tipo de calculo de zona actualizado.');
    }

    public function updateZoneKmExcess(Request $request, TrafficZone $zone): RedirectResponse
    {
        $this->authorize('viewAny', DriverLiquidationSetting::class);

        $data = $request->validate([
            'km_package_threshold' => ['nullable', 'integer', 'min:0'],
            'km_excess_package_amount' => ['nullable', 'numeric', 'min:0'],
        ]);

        $setting = DriverPaymentZoneSetting::firstOrNew([
            'traffic_zone_id' => $zone->id,
        ]);

        if (! $setting->exists && ! $setting->calc_type) {
            $setting->calc_type = DriverPaymentZoneSetting::TYPE_ZONA;
        }

        $setting->km_package_threshold = $this->nullableInt($data['km_package_threshold'] ?? null);
        $setting->km_excess_package_amount = $this->nullableDecimal($data['km_excess_package_amount'] ?? null);
        $setting->save();

        return redirect()->to(route('pago-choferes.settings.index') . '#km')->with('ok', 'Configuracion de paquete excedido actualizada.');
    }

    public function storeConcept(Request $request)
    {
        $this->authorize('viewAny', DriverLiquidationSetting::class);

        $data = $request->validate([
            'concept_name' => ['required', 'string', 'max:255', Rule::unique('driver_payment_concepts', 'name')],
            'value_type' => ['required', Rule::in($this->conceptValueTypes())],
            'default_amount' => ['nullable', 'numeric', 'min:0'],
            'sign' => ['nullable', Rule::in([-1, 1, '-1', '1'])],
            'reference_concept_id' => ['nullable', 'integer', 'exists:driver_payment_concepts,id'],
            'reference_multiplier' => ['nullable', 'numeric', 'gt:0'],
        ]);

        $concept = DriverPaymentConcept::create($this->buildConceptPayload($data));

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'data' => $concept,
            ], 201);
        }

        return redirect()->route('pago-choferes.settings.index')->with('ok', 'Concepto creado.');
    }

    public function updateConcept(Request $request, DriverPaymentConcept $concept)
    {
        $this->authorize('viewAny', DriverLiquidationSetting::class);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('driver_payment_concepts', 'name')->ignore($concept->id)],
            'value_type' => ['required', Rule::in($this->conceptValueTypes())],
            'default_amount' => ['nullable', 'numeric', 'min:0'],
            'sign' => ['nullable', Rule::in([-1, 1, '-1', '1'])],
            'reference_concept_id' => ['nullable', 'integer', 'exists:driver_payment_concepts,id'],
            'reference_multiplier' => ['nullable', 'numeric', 'gt:0'],
            'active' => ['nullable', 'boolean'],
        ]);

        $concept->update($this->buildConceptPayload($data, $concept));

        if ($request->expectsJson()) {
            $concept->load('referenceConcept:id,name');

            return response()->json([
                'ok' => true,
                'data' => $concept,
            ]);
        }

        return redirect()->route('pago-choferes.settings.index')->with('ok', 'Concepto actualizado.');
    }

    public function destroyConcept(Request $request, DriverPaymentConcept $concept)
    {
        $this->authorize('viewAny', DriverLiquidationSetting::class);

        $concept->delete();

        if ($request->expectsJson()) {
            return response()->json(['ok' => true]);
        }

        return redirect()->route('pago-choferes.settings.index')->with('ok', 'Concepto eliminado.');
    }

    public function storeFleet(Request $request)
    {
        $this->authorize('viewAny', DriverLiquidationSetting::class);

        $data = $this->validateFleet($request);

        $fleet = DriverPaymentFleet::create([
            'name' => trim((string) $data['name']),
            'billing_transportista_id' => $data['billing_transportista_id'] ?? null,
            'active' => array_key_exists('active', $data) ? (bool) $data['active'] : true,
        ]);
        $fleet->transportistas()->sync($data['transportista_ids'] ?? []);
        $fleet->load(['billingTransportista:id,name', 'transportistas:id,name']);

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'data' => $fleet], 201);
        }

        return redirect()->route('pago-choferes.settings.index')->with('ok', 'Flota creada.');
    }

    public function updateFleet(Request $request, DriverPaymentFleet $fleet)
    {
        $this->authorize('viewAny', DriverLiquidationSetting::class);

        $data = $this->validateFleet($request, $fleet);

        $fleet->update([
            'name' => trim((string) $data['name']),
            'billing_transportista_id' => $data['billing_transportista_id'] ?? null,
            'active' => array_key_exists('active', $data) ? (bool) $data['active'] : true,
        ]);
        $fleet->transportistas()->sync($data['transportista_ids'] ?? []);
        $fleet->load(['billingTransportista:id,name', 'transportistas:id,name']);

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'data' => $fleet]);
        }

        return redirect()->route('pago-choferes.settings.index')->with('ok', 'Flota actualizada.');
    }

    public function destroyFleet(Request $request, DriverPaymentFleet $fleet)
    {
        $this->authorize('viewAny', DriverLiquidationSetting::class);

        $fleet->delete();

        if ($request->expectsJson()) {
            return response()->json(['ok' => true]);
        }

        return redirect()->route('pago-choferes.settings.index')->with('ok', 'Flota eliminada.');
    }

    public function storePaymentType(Request $request): RedirectResponse
    {
        $this->authorize('viewAny', DriverLiquidationSetting::class);

        $data = $request->validate([
            'description' => ['required', 'string', 'max:255', Rule::unique('driver_payment_types', 'description')],
            'type' => ['required', 'boolean'],
        ]);

        DriverPaymentType::create([
            'description' => trim((string) $data['description']),
            'type' => (bool) $data['type'],
        ]);

        return redirect()->route('pago-choferes.settings.index')->with('ok', 'Tipo de pago creado.');
    }

    public function updatePaymentType(Request $request, DriverPaymentType $paymentType): RedirectResponse
    {
        $this->authorize('viewAny', DriverLiquidationSetting::class);

        $data = $request->validate([
            'description' => ['required', 'string', 'max:255', Rule::unique('driver_payment_types', 'description')->ignore($paymentType->id)],
            'type' => ['required', 'boolean'],
        ]);

        $paymentType->update([
            'description' => trim((string) $data['description']),
            'type' => (bool) $data['type'],
        ]);

        return redirect()->route('pago-choferes.settings.index')->with('ok', 'Tipo de pago actualizado.');
    }

    public function destroyPaymentType(DriverPaymentType $paymentType): RedirectResponse
    {
        $this->authorize('viewAny', DriverLiquidationSetting::class);

        $paymentType->delete();

        return redirect()->route('pago-choferes.settings.index')->with('ok', 'Tipo de pago eliminado.');
    }

    /**
     * @return RedirectResponse|\Illuminate\Http\JsonResponse
     */
    public function upsertZoneConcept(Request $request)
    {
        $this->authorize('viewAny', DriverLiquidationSetting::class);

        $data = $request->validate([
            'traffic_zone_id' => ['required', 'integer', 'exists:traffic_zones,id'],
            'driver_payment_concept_id' => ['required', 'integer', 'exists:driver_payment_concepts,id'],
            'vehicle_type' => ['required', Rule::in(array_keys(Transporte::paymentVehicleTypes(true)))],
            'value_type' => ['required', Rule::in($this->conceptValueTypes())],
            'monto_efectivo' => ['nullable', 'numeric', 'min:0'],
            'reference_concept_id' => ['nullable', 'integer', 'exists:driver_payment_concepts,id'],
            'reference_multiplier' => ['nullable', 'numeric', 'gt:0'],
            'active' => ['nullable', 'boolean'],
        ]);

        $concept = DriverPaymentConcept::findOrFail((int) $data['driver_payment_concept_id']);
        $payload = $this->buildZoneConceptPayload($data, $concept);

        DriverPaymentZoneConcept::updateOrCreate(
            [
                'traffic_zone_id' => (int) $data['traffic_zone_id'],
                'driver_payment_concept_id' => (int) $data['driver_payment_concept_id'],
                'vehicle_type' => $data['vehicle_type'],
            ],
            $payload
        );

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json(['message' => 'Guardado ok']);
        }

        return redirect()->route('pago-choferes.settings.index')->with('ok', 'Relacion zona/concepto guardada.');
    }

    public function destroyZoneConcept(DriverPaymentZoneConcept $zoneConcept): RedirectResponse
    {
        $this->authorize('viewAny', DriverLiquidationSetting::class);

        DriverPaymentZoneConceptYearValue::query()
            ->where('traffic_zone_id', $zoneConcept->traffic_zone_id)
            ->where('driver_payment_concept_id', $zoneConcept->driver_payment_concept_id)
            ->where('vehicle_type', $zoneConcept->vehicle_type)
            ->delete();

        $zoneConcept->delete();

        return redirect()->route('pago-choferes.settings.index')->with('ok', 'Relacion zona/concepto eliminada.');
    }

    public function storeZoneConceptYearValues(Request $request)
    {
        $this->authorize('viewAny', DriverLiquidationSetting::class);

        $data = $request->validate([
            'traffic_zone_id' => ['required', 'integer', 'exists:traffic_zones,id'],
            'driver_payment_concept_id' => ['required', 'integer', 'exists:driver_payment_concepts,id'],
            'vehicle_type' => ['required', Rule::in(array_keys(Transporte::paymentVehicleTypes(true)))],
            'rows' => ['nullable', 'array'],
            'rows.*.year_from' => ['required', 'integer', 'min:1900', 'max:2100'],
            'rows.*.year_to' => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'rows.*.amount' => ['required', 'numeric', 'min:0'],
        ]);

        $zone = TrafficZone::query()->findOrFail((int) $data['traffic_zone_id']);
        if (! $zone->uses_model_year_values) {
            throw ValidationException::withMessages([
                'traffic_zone_id' => 'La zona seleccionada no tiene habilitados valores por año.',
            ]);
        }

        DriverPaymentZoneConcept::updateOrCreate(
            [
                'traffic_zone_id' => (int) $data['traffic_zone_id'],
                'driver_payment_concept_id' => (int) $data['driver_payment_concept_id'],
                'vehicle_type' => $data['vehicle_type'],
            ],
            [
                'value_type' => DriverPaymentConcept::VALUE_FIXED,
                'monto_efectivo' => null,
                'reference_concept_id' => null,
                'reference_multiplier' => null,
                'active' => true,
            ]
        );

        $rows = collect($this->normalizeYearValueRows($data['rows'] ?? []));

        DriverPaymentZoneConceptYearValue::query()
            ->where('traffic_zone_id', (int) $data['traffic_zone_id'])
            ->where('driver_payment_concept_id', (int) $data['driver_payment_concept_id'])
            ->where('vehicle_type', $data['vehicle_type'])
            ->delete();

        foreach ($rows as $row) {
            DriverPaymentZoneConceptYearValue::create([
                'traffic_zone_id' => (int) $data['traffic_zone_id'],
                'driver_payment_concept_id' => (int) $data['driver_payment_concept_id'],
                'vehicle_type' => $data['vehicle_type'],
                'year_from' => $row['year_from'],
                'year_to' => $row['year_to'],
                'amount' => $row['amount'],
            ]);
        }

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'message' => 'Guardado ok',
                'rows' => $rows->values()->all(),
            ]);
        }

        return redirect()->route('pago-choferes.settings.index')->with('ok', 'Valores por año guardados.');
    }

    public function storePackageRateDefault(Request $request): RedirectResponse
    {
        $this->authorize('viewAny', DriverLiquidationSetting::class);

        $data = $request->validate([
            'package_rate_default' => ['required', 'numeric', 'min:0'],
        ]);

        DriverImportSetting::updateOrCreate(
            ['setting_key' => 'driver_payment_package_rate_default'],
            ['setting_value' => ['value' => (float) $data['package_rate_default']]]
        );

        return redirect()->route('pago-choferes.settings.index')->with('ok', 'Valor general por paquete actualizado.');
    }

    public function storeVehicleMap(Request $request)
    {
        $this->authorize('viewAny', DriverLiquidationSetting::class);

        $data = $request->validate([
            'excel_value' => ['required', 'string', 'max:255', Rule::unique('driver_import_vehicle_maps', 'excel_value')],
            'vehicle_type' => ['required', Rule::in(array_keys(Transporte::paymentVehicleTypes(true)))],
        ]);

        $map = DriverImportVehicleMap::create([
            'excel_value' => strtolower(trim((string) $data['excel_value'])),
            'vehicle_type' => $data['vehicle_type'],
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'data' => $map,
            ], 201);
        }

        return redirect()->route('pago-choferes.settings.index')->with('ok', 'Mapeo de vehiculo guardado.');
    }

    public function destroyVehicleMap(DriverImportVehicleMap $map)
    {
        $this->authorize('viewAny', DriverLiquidationSetting::class);

        $map->delete();

        if (request()->expectsJson()) {
            return response()->json([
                'ok' => true,
            ]);
        }

        return redirect()->route('pago-choferes.settings.index')->with('ok', 'Mapeo de vehiculo eliminado.');
    }

    public function storeDefaultPeriodType(Request $request): RedirectResponse
    {
        $this->authorize('viewAny', DriverLiquidationSetting::class);

        $data = $request->validate([
            'default_period_type' => ['required', Rule::in(['quincenal', 'mensual'])],
        ]);

        DriverImportSetting::updateOrCreate(
            ['setting_key' => 'driver_payment_default_period_type'],
            ['setting_value' => $data['default_period_type']]
        );

        return redirect()->route('pago-choferes.settings.index')->with('ok', 'Tipo de liquidacion general actualizado.');
    }

    public function storeLogisticsImportMode(Request $request): RedirectResponse
    {
        $this->authorize('viewAny', DriverLiquidationSetting::class);

        $data = $request->validate([
            'logistics_import_mode' => ['required', Rule::in(['replace', 'merge'])],
        ]);

        DriverImportSetting::updateOrCreate(
            ['setting_key' => 'driver_payment_logistics_import_mode'],
            ['setting_value' => $data['logistics_import_mode']]
        );

        return redirect()->route('pago-choferes.settings.index')->with('ok', 'Modo general de importación de logística actualizado.');
    }

    public function storeNewDriversConfig(Request $request): RedirectResponse
    {
        $this->authorize('viewAny', DriverLiquidationSetting::class);

        $data = $request->validate([
            'new_driver_days' => ['required', 'integer', 'min:0'],
            'new_driver_color' => ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ]);

        DriverImportSetting::updateOrCreate(
            ['setting_key' => 'driver_new_days'],
            ['setting_value' => (int) $data['new_driver_days']]
        );

        DriverImportSetting::updateOrCreate(
            ['setting_key' => 'driver_new_color'],
            ['setting_value' => $data['new_driver_color']]
        );

        return redirect()->back()->with('ok', 'Configuración de choferes nuevos actualizada.');
    }

    public function storeKmRange(Request $request): RedirectResponse
    {
        $this->authorize('viewAny', DriverLiquidationSetting::class);

        $data = $request->validate([
            'traffic_zone_id' => ['nullable', 'integer', 'exists:traffic_zones,id'],
            'vehicle_type' => ['required', Rule::in(array_keys(Transporte::paymentVehicleTypes(true)))],
            'km_from' => ['required', 'numeric', 'min:0'],
            'km_to' => ['required', 'numeric', 'gte:km_from'],
            'amount' => ['required', 'numeric', 'min:0'],
            'active' => ['nullable', 'boolean'],
        ]);

        DriverPaymentKmRange::create([
            'traffic_zone_id' => $data['traffic_zone_id'] ?? null,
            'vehicle_type' => $data['vehicle_type'],
            'km_from' => (float) $data['km_from'],
            'km_to' => (float) $data['km_to'],
            'amount' => (float) $data['amount'],
            'active' => array_key_exists('active', $data) ? (bool) $data['active'] : true,
        ]);

        return redirect()->route('pago-choferes.settings.index')->with('ok', 'Rango KM guardado.');
    }

    public function destroyKmRange(DriverPaymentKmRange $range): RedirectResponse
    {
        $this->authorize('viewAny', DriverLiquidationSetting::class);

        $range->delete();

        return redirect()->route('pago-choferes.settings.index')->with('ok', 'Rango KM eliminado.');
    }

    public function storeAdjustmentRule(Request $request): RedirectResponse
    {
        $this->authorize('viewAny', DriverLiquidationSetting::class);

        $data = $this->validateAdjustmentRule($request);

        DriverPaymentAdjustmentRule::create($data);

        return redirect()->route('pago-choferes.settings.index')->with('ok', 'Ajuste automatico guardado.');
    }

    public function updateAdjustmentRule(Request $request, DriverPaymentAdjustmentRule $rule): RedirectResponse
    {
        $this->authorize('viewAny', DriverLiquidationSetting::class);

        $rule->update($this->validateAdjustmentRule($request));

        return redirect()->route('pago-choferes.settings.index')->with('ok', 'Ajuste automatico actualizado.');
    }

    public function destroyAdjustmentRule(DriverPaymentAdjustmentRule $rule): RedirectResponse
    {
        $this->authorize('viewAny', DriverLiquidationSetting::class);

        $rule->delete();

        return redirect()->route('pago-choferes.settings.index')->with('ok', 'Ajuste automatico eliminado.');
    }

    private function buildConceptPayload(array $data, ?DriverPaymentConcept $concept = null): array
    {
        $valueType = $data['value_type'];
        $referenceConceptId = $data['reference_concept_id'] ?? null;

        if ($concept && $referenceConceptId !== null && (int) $referenceConceptId === (int) $concept->id) {
            throw ValidationException::withMessages([
                'reference_concept_id' => 'El concepto no puede referenciarse a si mismo.',
            ]);
        }

        if ($valueType === DriverPaymentConcept::VALUE_REFERENCE && ! $referenceConceptId) {
            throw ValidationException::withMessages([
                'reference_concept_id' => 'Debes elegir el concepto base para una referencia.',
            ]);
        }

        if ($valueType === DriverPaymentConcept::VALUE_FIXED && (! array_key_exists('default_amount', $data) || $data['default_amount'] === null || $data['default_amount'] === '')) {
            $data['default_amount'] = 0;
        }

        return [
            'name' => trim((string) ($data['name'] ?? $data['concept_name'])),
            'value_type' => $valueType,
            'default_amount' => $valueType === DriverPaymentConcept::VALUE_FIXED
                ? $this->nullableDecimal($data['default_amount'] ?? null)
                : null,
            'sign' => isset($data['sign']) && (int) $data['sign'] === -1 ? -1 : 1,
            'reference_concept_id' => $valueType === DriverPaymentConcept::VALUE_REFERENCE
                ? (int) $referenceConceptId
                : null,
            'reference_multiplier' => $valueType === DriverPaymentConcept::VALUE_REFERENCE
                ? ($this->nullableDecimal($data['reference_multiplier'] ?? null) ?? 1.0)
                : null,
            'active' => array_key_exists('active', $data) ? (bool) $data['active'] : true,
        ];
    }

    private function buildZoneConceptPayload(array $data, DriverPaymentConcept $concept): array
    {
        $valueType = $data['value_type'];
        $referenceConceptId = $data['reference_concept_id'] ?? null;

        if ($referenceConceptId !== null && (int) $referenceConceptId === (int) $concept->id) {
            throw ValidationException::withMessages([
                'reference_concept_id' => 'La relacion no puede referenciarse al mismo concepto.',
            ]);
        }

        if ($valueType === DriverPaymentConcept::VALUE_REFERENCE && ! $referenceConceptId) {
            throw ValidationException::withMessages([
                'reference_concept_id' => 'Debes elegir el concepto base para una referencia.',
            ]);
        }

        $amount = $this->nullableDecimal($data['monto_efectivo'] ?? null);
        if ($valueType === DriverPaymentConcept::VALUE_FIXED && $amount === null) {
            $amount = $concept->default_amount !== null ? (float) $concept->default_amount : null;
        }

        if ($valueType === DriverPaymentConcept::VALUE_FIXED && $amount === null) {
            throw ValidationException::withMessages([
                'monto_efectivo' => 'Debes indicar el valor fijo o definir uno por defecto en el concepto.',
            ]);
        }

        return [
            'value_type' => $valueType,
            'monto_efectivo' => $valueType === DriverPaymentConcept::VALUE_FIXED ? $amount : 0,
            'reference_concept_id' => $valueType === DriverPaymentConcept::VALUE_REFERENCE
                ? (int) $referenceConceptId
                : null,
            'reference_multiplier' => $valueType === DriverPaymentConcept::VALUE_REFERENCE
                ? ($this->nullableDecimal($data['reference_multiplier'] ?? null) ?? 1.0)
                : null,
            'active' => array_key_exists('active', $data) ? (bool) $data['active'] : true,
        ];
    }

    private function conceptValueTypes(): array
    {
        return [
            DriverPaymentConcept::VALUE_FIXED,
            DriverPaymentConcept::VALUE_REFERENCE,
        ];
    }

    private function validateFleet(Request $request, ?DriverPaymentFleet $fleet = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('driver_payment_fleets', 'name')->ignore(optional($fleet)->id)],
            'billing_transportista_id' => ['nullable', 'integer', 'exists:transportistas,id'],
            'transportista_ids' => ['nullable', 'array'],
            'transportista_ids.*' => ['integer', 'distinct', 'exists:transportistas,id'],
            'active' => ['nullable', 'boolean'],
        ]);

        $memberIds = collect($data['transportista_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($memberIds->isEmpty()) {
            throw ValidationException::withMessages([
                'transportista_ids' => 'Debes seleccionar al menos un chofer para la flota.',
            ]);
        }

        if (! empty($data['billing_transportista_id']) && ! $memberIds->contains((int) $data['billing_transportista_id'])) {
            $memberIds->push((int) $data['billing_transportista_id']);
            $memberIds = $memberIds->unique()->values();
        }

        $conflictingFleet = DriverPaymentFleet::query()
            ->whereHas('transportistas', function ($query) use ($memberIds) {
                $query->whereIn('transportistas.id', $memberIds->all());
            });

        if ($fleet) {
            $conflictingFleet->where('id', '!=', $fleet->id);
        }

        $existing = $conflictingFleet->first();
        if ($existing) {
            throw ValidationException::withMessages([
                'transportista_ids' => 'Uno de los choferes seleccionados ya pertenece a la flota "' . $existing->name . '".',
            ]);
        }

        $data['transportista_ids'] = $memberIds->all();

        return $data;
    }

    private function normalizeYearValueRows(array $rows): array
    {
        $normalized = collect($rows)
            ->map(function (array $row, int $index) {
                $yearFrom = (int) ($row['year_from'] ?? 0);
                $yearTo = array_key_exists('year_to', $row) && $row['year_to'] !== null && $row['year_to'] !== ''
                    ? (int) $row['year_to']
                    : null;

                if ($yearTo !== null && $yearTo < $yearFrom) {
                    throw ValidationException::withMessages([
                        "rows.$index.year_to" => 'El año hasta debe ser mayor o igual al año desde.',
                    ]);
                }

                return [
                    'year_from' => $yearFrom,
                    'year_to' => $yearTo,
                    'amount' => (float) $row['amount'],
                ];
            })
            ->sort(function (array $left, array $right) {
                $leftTo = $left['year_to'] ?? PHP_INT_MAX;
                $rightTo = $right['year_to'] ?? PHP_INT_MAX;

                return [$left['year_from'], $leftTo] <=> [$right['year_from'], $rightTo];
            })
            ->values()
            ->all();

        $previous = null;
        foreach ($normalized as $index => $row) {
            if ($previous === null) {
                $previous = $row;
                continue;
            }

            $previousTo = $previous['year_to'] ?? PHP_INT_MAX;
            if ($row['year_from'] <= $previousTo) {
                throw ValidationException::withMessages([
                    "rows.$index.year_from" => 'Los rangos de años no pueden superponerse.',
                ]);
            }

            $previous = $row;
        }

        return $normalized;
    }

    private function abortIfOverlapping(?int $excludeId, array $data): void
    {
        $desde = (int) ($data['desde'] ?? 0);
        $hasta = (int) ($data['hasta'] ?? 0);
        $quincena = isset($data['quincena']) && $data['quincena'] !== '' ? (int) $data['quincena'] : null;

        $candidates = DriverLiquidationSetting::query()
            ->where('tipo_periodo', $data['tipo_periodo']);

        if ($excludeId) {
            $candidates->where('id', '!=', $excludeId);
        }

        foreach ($candidates->get() as $existing) {
            $existingQuincena = $existing->quincena !== null ? (int) $existing->quincena : null;

            // En quincenal, distintas quincenas no colisionan.
            if ($data['tipo_periodo'] === 'quincenal') {
                $sameQuincenaScope = ($quincena === null && $existingQuincena === null)
                    || ($quincena !== null && $existingQuincena !== null && $quincena === $existingQuincena);
                if (! $sameQuincenaScope) {
                    continue;
                }
            }

            if (! $existing->activo) {
                continue;
            }

            $existingDesde = (int) $existing->desde;
            $existingHasta = (int) $existing->hasta;
            $overlap = $desde <= $existingHasta && $hasta >= $existingDesde;

            if ($overlap) {
                abort(422, 'El rango de dias se superpone con otra configuracion del mismo tipo.');
            }
        }
    }

    private function normalizeDay(int $day): int
    {
        return max(1, min(31, $day));
    }

    private function resolvePackageRateDefault(): float
    {
        $record = DriverImportSetting::where('setting_key', 'driver_payment_package_rate_default')->first();
        $value = is_array(optional($record)->setting_value) ? ($record->setting_value['value'] ?? null) : null;

        return is_numeric($value) ? (float) $value : 0.0;
    }

    private function saveColumnMap(Request $request): void
    {
        $raw = trim((string) $request->input('column_map_json', ''));
        if ($raw === '') {
            return;
        }

        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            abort(422, 'El JSON de mapeo de columnas es invalido.');
        }

        DriverImportSetting::updateOrCreate(
            ['setting_key' => 'excel_column_map'],
            ['setting_value' => $decoded]
        );
    }

    private function validateAdjustmentRule(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0'],
            'sign' => ['required', Rule::in([-1, 1])],
            'traffic_zone_id' => ['nullable', 'integer', 'exists:traffic_zones,id'],
            'transportista_id' => ['nullable', 'integer', 'exists:transportistas,id'],
            'vehicle_type' => ['required', Rule::in(array_keys(Transporte::paymentVehicleTypes(true)))],
            'active' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string'],
        ]);

        return [
            'name' => trim((string) $data['name']),
            'amount' => (float) $data['amount'],
            'sign' => (int) $data['sign'],
            'traffic_zone_id' => $data['traffic_zone_id'] ?? null,
            'transportista_id' => $data['transportista_id'] ?? null,
            'vehicle_type' => $data['vehicle_type'],
            'active' => array_key_exists('active', $data) ? (bool) $data['active'] : true,
            'notes' => $data['notes'] ?? null,
        ];
    }

    public function storeVehicleAliases(Request $request)
    {
        $this->authorize('viewAny', DriverLiquidationSetting::class);

        $data = $request->validate([
            'aliases' => ['required', 'array'],
            'aliases.moto' => ['required', 'string', 'max:255'],
            'aliases.camioneta' => ['required', 'string', 'max:255'],
            'aliases.camioneta_mediana' => ['required', 'string', 'max:255'],
            'aliases.camioneta_grande' => ['required', 'string', 'max:255'],
            'aliases.general' => ['required', 'string', 'max:255'],
        ]);

        DriverImportSetting::updateOrCreate(
            ['setting_key' => 'vehicle_type_aliases'],
            ['setting_value' => $data['aliases']]
        );

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'ok' => true,
                'message' => 'Alias de tipos de vehículos actualizados.'
            ]);
        }

        return redirect()->route('pago-choferes.reglas.index')->with('ok', 'Alias de tipos de vehículos actualizados.');
    }

    private function nullableDecimal($value): ?float
    {
        return $value === null || $value === '' ? null : (float) $value;
    }

    private function nullableInt($value): ?int
    {
        return $value === null || $value === '' ? null : (int) $value;
    }
}
