<?php

namespace Tests\Feature;

use App\Models\DriverPaymentConcept;
use App\Models\DriverPaymentKmRange;
use App\Models\DriverPaymentZoneConceptYearValue;
use App\Models\DriverPaymentZoneConcept;
use App\Models\DriverPaymentZoneSetting;
use App\Models\DriverLiquidationSetting;
use App\Models\PlanillaPagoChofer;
use App\Models\PlanillaPagoChoferRecibo;
use App\Models\ReciboChofer;
use App\Models\TrafficZone;
use App\Models\Transporte;
use App\Models\Transportista;
use App\Services\DriverPayments\DriverExcelImporter;
use App\Services\DriverPayments\PlanillaBankResultImporter;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class DriverPaymentsModuleTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // IMPORTANT: isolate this suite from the real MySQL database.
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', ':memory:');
        DB::purge('sqlite');
        DB::reconnect('sqlite');

        if (DB::connection()->getDriverName() !== 'sqlite') {
            throw new \RuntimeException('DriverPaymentsModuleTest must run on sqlite only.');
        }

        $this->prepareSchema();
        $this->seedFixedRule('Fallback', 100.0, ['concept_key' => ['viaje', 'ruta ruta 1 #100']], 0);
    }

    public function test_excel_import_deduplicates_items_by_source_key(): void
    {
        DriverLiquidationSetting::create([
            'tipo_periodo' => 'mensual',
            'anio' => 2026,
            'mes' => 2,
            'desde' => '2026-02-01',
            'hasta' => '2026-02-28',
            'activo' => true,
        ]);

        $file = $this->buildTrafficWorkbook();
        $importer = app(DriverExcelImporter::class);

        $firstRun = $importer->import($file);
        $this->assertSame(1, ReciboChofer::count());
        $this->assertSame(1, \App\Models\ReciboChoferItem::count());
        $this->assertEquals(1, $firstRun->rows_created);

        $secondFile = $this->buildTrafficWorkbook();
        $secondRun = $importer->import($secondFile);

        $this->assertSame(1, \App\Models\ReciboChoferItem::count());
        $this->assertGreaterThanOrEqual(1, $secondRun->rows_updated);
    }

    public function test_bank_result_import_updates_receipts_and_planilla_status(): void
    {
        \App\Models\DriverPaymentType::create([
            'description' => 'PAGADO',
            'type' => true,
        ]);
        \App\Models\DriverPaymentType::create([
            'description' => 'NO_PAGADO',
            'type' => false,
        ]);

        $transportista = Transportista::create([
            'name' => 'Chofer Test',
            'is_active' => true,
        ]);

        $reciboPaid = ReciboChofer::create([
            'transportista_id' => $transportista->id,
            'tipo_periodo' => 'mensual',
            'periodo_desde' => '2026-02-01',
            'periodo_hasta' => '2026-02-28',
            'fecha_emision' => '2026-03-01',
            'estado' => ReciboChofer::ESTADO_EN_PLANILLA,
            'importe_total' => 1000,
        ]);

        $reciboPending = ReciboChofer::create([
            'transportista_id' => $transportista->id,
            'tipo_periodo' => 'mensual',
            'periodo_desde' => '2026-02-01',
            'periodo_hasta' => '2026-02-28',
            'fecha_emision' => '2026-03-01',
            'estado' => ReciboChofer::ESTADO_EN_PLANILLA,
            'importe_total' => 2000,
        ]);

        $planilla = PlanillaPagoChofer::create([
            'numero' => 'PC-202602-0001',
            'fecha' => '2026-03-05',
            'estado' => PlanillaPagoChofer::ESTADO_CONFIRMADA,
            'total' => 3000,
        ]);

        PlanillaPagoChoferRecibo::create([
            'planilla_pago_chofer_id' => $planilla->id,
            'recibo_chofer_id' => $reciboPaid->id,
            'monto_en_planilla' => 1000,
            'estado_pago' => PlanillaPagoChoferRecibo::ESTADO_SIN_RESULTADO,
        ]);

        PlanillaPagoChoferRecibo::create([
            'planilla_pago_chofer_id' => $planilla->id,
            'recibo_chofer_id' => $reciboPending->id,
            'monto_en_planilla' => 2000,
            'estado_pago' => PlanillaPagoChoferRecibo::ESTADO_SIN_RESULTADO,
        ]);

        $file = $this->buildBankResultWorkbook($reciboPaid->id, $reciboPending->id);
        $importer = app(PlanillaBankResultImporter::class);
        $result = $importer->import($planilla, $file);

        $this->assertEquals(2, $result['updated']);
        $this->assertSame(ReciboChofer::ESTADO_PAGADO, $reciboPaid->fresh()->estado);
        $this->assertSame(ReciboChofer::ESTADO_PENDIENTE_PAGO, $reciboPending->fresh()->estado);
        $this->assertSame(PlanillaPagoChofer::ESTADO_PAGADA_PARCIAL, $planilla->fresh()->estado);
    }

    public function test_excel_import_applies_configured_concepts_across_zona_km_and_paquete(): void
    {
        $this->seedFixedRule('Medio Rule', 70.0, ['concept_key' => 'MEDIO']);
        $this->seedFixedRule('KM', 95.0, [], 10, 'receipt');
        $this->seedFixedRule('PAQUETE', 33.0, [], 10, 'receipt');

        DriverLiquidationSetting::create([
            'tipo_periodo' => 'mensual',
            'anio' => 2026,
            'mes' => 2,
            'desde' => '2026-02-01',
            'hasta' => '2026-02-28',
            'activo' => true,
        ]);

        $transportista = Transportista::create([
            'name' => 'Juan Perez',
            'is_active' => true,
        ]);

        Transporte::create([
            'transportista_id' => $transportista->id,
            'license_plate' => 'AAA111',
            'type' => Transporte::TYPE_MOTO,
            'is_default' => true,
            'is_active' => true,
        ]);

        $zoneSheet = TrafficZone::create(['name' => 'AMBA ZONA']);
        $kmSheet = TrafficZone::create(['name' => 'AMBA KM']);
        $packageSheet = TrafficZone::create(['name' => 'AMBA PAQ']);

        DriverPaymentZoneSetting::create([
            'traffic_zone_id' => $zoneSheet->id,
            'calc_type' => DriverPaymentZoneSetting::TYPE_ZONA,
        ]);
        DriverPaymentZoneSetting::create([
            'traffic_zone_id' => $kmSheet->id,
            'calc_type' => DriverPaymentZoneSetting::TYPE_KM,
        ]);
        DriverPaymentZoneSetting::create([
            'traffic_zone_id' => $packageSheet->id,
            'calc_type' => DriverPaymentZoneSetting::TYPE_PAQUETE,
        ]);

        $baseConcept = DriverPaymentConcept::create([
            'name' => 'BASE',
            'value_type' => DriverPaymentConcept::VALUE_FIXED,
            'default_amount' => 100,
            'active' => true,
        ]);

        DriverPaymentConcept::create([
            'name' => 'MEDIO',
            'value_type' => DriverPaymentConcept::VALUE_REFERENCE,
            'reference_concept_id' => $baseConcept->id,
            'reference_multiplier' => 0.5,
            'active' => true,
        ]);

        $kmConcept = DriverPaymentConcept::create([
            'name' => 'KM',
            'value_type' => DriverPaymentConcept::VALUE_FIXED,
            'default_amount' => 80,
            'active' => true,
        ]);

        $packageConcept = DriverPaymentConcept::create([
            'name' => 'PAQUETE',
            'value_type' => DriverPaymentConcept::VALUE_FIXED,
            'default_amount' => 30,
            'active' => true,
        ]);

        DriverPaymentZoneConcept::create([
            'traffic_zone_id' => $zoneSheet->id,
            'driver_payment_concept_id' => $baseConcept->id,
            'vehicle_type' => 'general',
            'value_type' => DriverPaymentConcept::VALUE_FIXED,
            'monto_efectivo' => 120,
            'active' => true,
        ]);
        DriverPaymentZoneConcept::create([
            'traffic_zone_id' => $zoneSheet->id,
            'driver_payment_concept_id' => $baseConcept->id,
            'vehicle_type' => Transporte::TYPE_MOTO,
            'value_type' => DriverPaymentConcept::VALUE_FIXED,
            'monto_efectivo' => 140,
            'active' => true,
        ]);
        DriverPaymentZoneConcept::create([
            'traffic_zone_id' => $kmSheet->id,
            'driver_payment_concept_id' => $kmConcept->id,
            'vehicle_type' => 'general',
            'value_type' => DriverPaymentConcept::VALUE_FIXED,
            'monto_efectivo' => 90,
            'active' => true,
        ]);
        DriverPaymentZoneConcept::create([
            'traffic_zone_id' => $kmSheet->id,
            'driver_payment_concept_id' => $kmConcept->id,
            'vehicle_type' => Transporte::TYPE_MOTO,
            'value_type' => DriverPaymentConcept::VALUE_FIXED,
            'monto_efectivo' => 95,
            'active' => true,
        ]);
        DriverPaymentZoneConcept::create([
            'traffic_zone_id' => $packageSheet->id,
            'driver_payment_concept_id' => $packageConcept->id,
            'vehicle_type' => 'general',
            'value_type' => DriverPaymentConcept::VALUE_FIXED,
            'monto_efectivo' => 31,
            'active' => true,
        ]);
        DriverPaymentZoneConcept::create([
            'traffic_zone_id' => $packageSheet->id,
            'driver_payment_concept_id' => $packageConcept->id,
            'vehicle_type' => Transporte::TYPE_MOTO,
            'value_type' => DriverPaymentConcept::VALUE_FIXED,
            'monto_efectivo' => 33,
            'active' => true,
        ]);

        $file = $this->buildConfiguredConceptWorkbook();
        $importer = app(DriverExcelImporter::class);
        $importer->import($file);

        $this->assertSame(1, ReciboChofer::count());
        $this->assertSame(5, \App\Models\ReciboChoferItem::count());

        $zonaItem = \App\Models\ReciboChoferItem::where('concepto', 'MEDIO')->first();
        $kmItem = \App\Models\ReciboChoferItem::where('concepto', 'KM')->first();
        $packageItem = \App\Models\ReciboChoferItem::where('concepto', 'PAQUETE')->first();

        $this->assertNotNull($zonaItem);
        $this->assertNotNull($kmItem);
        $this->assertNotNull($packageItem);

        $this->assertSame('70.00', $zonaItem->importe);
        $this->assertSame('95.00', $kmItem->importe);
        $this->assertSame('33.00', $packageItem->importe);
    }

    public function test_excel_import_applies_km_package_excess_only_for_motorcycles(): void
    {
        $this->seedFixedRule('Moto Rule', 150.0, ['vehicle_type' => 'moto']);
        $this->seedFixedRule('Van Rule', 100.0, ['vehicle_type' => 'camioneta']);

        DriverLiquidationSetting::create([
            'tipo_periodo' => 'mensual',
            'anio' => 2026,
            'mes' => 2,
            'desde' => '2026-02-01',
            'hasta' => '2026-02-28',
            'activo' => true,
        ]);

        $motoDriver = Transportista::create([
            'name' => 'Moto Test',
            'is_active' => true,
        ]);

        $vanDriver = Transportista::create([
            'name' => 'Van Test',
            'is_active' => true,
        ]);

        Transporte::create([
            'transportista_id' => $motoDriver->id,
            'license_plate' => 'MOTO123',
            'type' => Transporte::TYPE_MOTO,
            'is_default' => true,
            'is_active' => true,
        ]);

        Transporte::create([
            'transportista_id' => $vanDriver->id,
            'license_plate' => 'VAN123',
            'type' => Transporte::TYPE_CAMIONETA,
            'is_default' => true,
            'is_active' => true,
        ]);

        $kmZone = TrafficZone::create(['name' => 'AMBA KM']);

        DriverPaymentZoneSetting::create([
            'traffic_zone_id' => $kmZone->id,
            'calc_type' => DriverPaymentZoneSetting::TYPE_KM,
            'km_package_threshold' => 30,
            'km_excess_package_amount' => 10,
        ]);

        DriverPaymentKmRange::create([
            'traffic_zone_id' => $kmZone->id,
            'vehicle_type' => 'general',
            'km_from' => 0,
            'km_to' => 20,
            'amount' => 100,
            'active' => true,
        ]);

        $file = $this->buildKmExcessWorkbook();
        $importer = app(DriverExcelImporter::class);
        $importer->import($file);

        $motoReceipt = ReciboChofer::where('transportista_id', $motoDriver->id)->firstOrFail();
        $vanReceipt = ReciboChofer::where('transportista_id', $vanDriver->id)->firstOrFail();

        $motoItem = \App\Models\ReciboChoferItem::where('recibo_chofer_id', $motoReceipt->id)->first();
        $vanItem = \App\Models\ReciboChoferItem::where('recibo_chofer_id', $vanReceipt->id)->first();

        $this->assertNotNull($motoItem);
        $this->assertNotNull($vanItem);
        $this->assertSame('150.00', $motoItem->importe);
        $this->assertSame('100.00', $vanItem->importe);
    }

    public function test_excel_import_applies_zone_value_by_model_year_when_zone_requires_it(): void
    {
        $this->seedFixedRule('Year Rule', 185.0, ['model_year' => 2023]);

        DriverLiquidationSetting::create([
            'tipo_periodo' => 'mensual',
            'anio' => 2026,
            'mes' => 2,
            'desde' => '2026-02-01',
            'hasta' => '2026-02-28',
            'activo' => true,
        ]);

        $transportista = Transportista::create([
            'name' => 'Modelo Test',
            'is_active' => true,
        ]);

        Transporte::create([
            'transportista_id' => $transportista->id,
            'license_plate' => 'MOD123',
            'type' => Transporte::TYPE_CAMIONETA,
            'is_default' => true,
            'is_active' => true,
        ]);

        $zone = TrafficZone::create([
            'name' => 'CORDOBA',
            'uses_model_year_values' => true,
        ]);

        DriverPaymentZoneSetting::create([
            'traffic_zone_id' => $zone->id,
            'calc_type' => DriverPaymentZoneSetting::TYPE_ZONA,
        ]);

        $concept = DriverPaymentConcept::create([
            'name' => 'ZONA 1',
            'value_type' => DriverPaymentConcept::VALUE_FIXED,
            'default_amount' => 100,
            'active' => true,
        ]);

        DriverPaymentZoneConcept::create([
            'traffic_zone_id' => $zone->id,
            'driver_payment_concept_id' => $concept->id,
            'vehicle_type' => Transporte::TYPE_CAMIONETA,
            'value_type' => DriverPaymentConcept::VALUE_FIXED,
            'monto_efectivo' => 120,
            'active' => true,
        ]);

        DriverPaymentZoneConceptYearValue::create([
            'traffic_zone_id' => $zone->id,
            'driver_payment_concept_id' => $concept->id,
            'vehicle_type' => Transporte::TYPE_CAMIONETA,
            'year_from' => 2023,
            'year_to' => 2023,
            'amount' => 185,
        ]);

        $file = $this->buildZoneYearWorkbook();
        $importer = app(DriverExcelImporter::class);
        $importer->import($file);

        $item = \App\Models\ReciboChoferItem::where('concepto', 'ZONA 1')->first();
        $this->assertNotNull($item);
        $this->assertSame('185.00', $item->importe);
        $this->assertSame(2023, $item->meta['model_year']);
    }

    private function prepareSchema(): void
    {
        $tables = [
            'transportista_payment_methods',
            'system_parameters',
            'settlement_rule_actions',
            'settlement_rule_conditions',
            'settlement_rules',
            'driver_payment_fleet_transportista',
            'driver_payment_fleets',
            'planilla_pago_chofer_recibos',
            'planillas_pago_chofer',
            'recibo_chofer_items',
            'recibos_chofer',
            'transportista_liquidation_meta',
            'driver_import_run_rows',
            'driver_import_runs',
            'driver_import_settings',
            'driver_import_vehicle_maps',
            'driver_liquidation_settings',
            'driver_payment_km_ranges',
            'driver_payment_zone_concept_year_values',
            'driver_payment_zone_concepts',
            'driver_payment_concepts',
            'driver_payment_zone_settings',
            'driver_payment_types',
            'traffic_zones',
            'transportes',
            'transportistas',
            'users',
        ];

        foreach ($tables as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('password')->nullable();
            $table->string('role')->nullable();
            $table->timestamps();
        });

        Schema::create('transportistas', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->string('base_location')->nullable();
            $table->string('tax_id')->nullable();
            $table->string('cbu')->nullable();
            $table->string('email')->nullable();
            $table->timestamps();
        });

        Schema::create('transportes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('transportista_id');
            $table->string('license_plate')->nullable();
            $table->string('type')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        Schema::create('traffic_zones', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type')->nullable();
            $table->string('priority')->nullable();
            $table->boolean('is_soft')->default(true);
            $table->decimal('center_lat', 10, 7)->nullable();
            $table->decimal('center_lng', 10, 7)->nullable();
            $table->decimal('radius_km', 10, 2)->nullable();
            $table->text('polygon')->nullable();
            $table->boolean('uses_model_year_values')->default(false);
            $table->timestamps();
        });

        Schema::create('driver_liquidation_settings', function (Blueprint $table) {
            $table->id();
            $table->string('tipo_periodo');
            $table->unsignedSmallInteger('anio')->nullable();
            $table->unsignedTinyInteger('mes')->nullable();
            $table->unsignedTinyInteger('quincena')->nullable();
            $table->date('desde');
            $table->date('hasta');
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('driver_payment_concepts', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('value_type')->default('fixed');
            $table->decimal('default_amount', 14, 2)->nullable();
            $table->unsignedBigInteger('reference_concept_id')->nullable();
            $table->decimal('reference_multiplier', 14, 4)->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('driver_payment_zone_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('traffic_zone_id');
            $table->string('calc_type')->default('ZONA');
            $table->decimal('package_rate', 14, 4)->nullable();
            $table->unsignedInteger('km_package_threshold')->nullable();
            $table->decimal('km_excess_package_amount', 14, 2)->nullable();
            $table->decimal('km_remote_zone_plus_large', 14, 2)->nullable();
            $table->decimal('package_delivered_rate', 14, 4)->nullable();
            $table->decimal('package_absent_rate_multiplier', 8, 4)->nullable();
            $table->decimal('package_fixed_amount', 14, 2)->nullable();
            $table->unsignedInteger('package_excess_threshold')->nullable();
            $table->decimal('package_excess_amount', 14, 2)->nullable();
            $table->timestamps();
        });

        Schema::create('driver_payment_zone_concepts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('traffic_zone_id');
            $table->unsignedBigInteger('driver_payment_concept_id');
            $table->string('vehicle_type')->default('general');
            $table->string('value_type')->default('fixed');
            $table->decimal('monto_efectivo', 14, 2)->nullable();
            $table->unsignedBigInteger('reference_concept_id')->nullable();
            $table->decimal('reference_multiplier', 14, 4)->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('driver_payment_zone_concept_year_values', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('traffic_zone_id');
            $table->unsignedBigInteger('driver_payment_concept_id');
            $table->string('vehicle_type');
            $table->unsignedSmallInteger('year_from');
            $table->unsignedSmallInteger('year_to');
            $table->decimal('amount', 14, 2);
            $table->timestamps();
        });

        Schema::create('driver_payment_fleets', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedBigInteger('billing_transportista_id')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('driver_payment_fleet_transportista', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('driver_payment_fleet_id');
            $table->unsignedBigInteger('transportista_id');
            $table->timestamps();
        });

        Schema::create('driver_payment_km_ranges', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('traffic_zone_id')->nullable();
            $table->string('vehicle_type')->default('general');
            $table->decimal('km_from', 14, 3);
            $table->decimal('km_to', 14, 3);
            $table->decimal('amount', 14, 2);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('driver_import_settings', function (Blueprint $table) {
            $table->id();
            $table->string('setting_key')->unique();
            $table->json('setting_value')->nullable();
            $table->timestamps();
        });

        Schema::create('driver_import_vehicle_maps', function (Blueprint $table) {
            $table->id();
            $table->string('excel_value')->unique();
            $table->string('vehicle_type');
            $table->timestamps();
        });

        Schema::create('driver_payment_types', function (Blueprint $table) {
            $table->id();
            $table->string('description');
            $table->boolean('type')->default(false);
            $table->timestamps();
        });

        Schema::create('driver_import_runs', function (Blueprint $table) {
            $table->id();
            $table->string('source_file')->nullable();
            $table->string('tipo_periodo');
            $table->unsignedInteger('rows_processed')->default(0);
            $table->unsignedInteger('rows_created')->default(0);
            $table->unsignedInteger('rows_updated')->default(0);
            $table->unsignedInteger('rows_with_errors')->default(0);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->json('summary')->nullable();
            $table->timestamps();
        });

        Schema::create('driver_import_run_rows', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('driver_import_run_id');
            $table->string('sheet_name')->nullable();
            $table->unsignedInteger('row_number')->nullable();
            $table->string('status');
            $table->string('message')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();
        });

        Schema::create('recibos_chofer', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('transportista_id');
            $table->unsignedBigInteger('driver_payment_fleet_id')->nullable();
            $table->string('tipo_periodo');
            $table->date('periodo_desde');
            $table->date('periodo_hasta');
            $table->date('fecha_emision')->nullable();
            $table->string('plaza')->nullable();
            $table->string('estado')->default('cargado');
            $table->decimal('importe_total', 14, 2)->default(0);
            $table->unsignedBigInteger('factura_id')->nullable();
            $table->string('factura_ref')->nullable();
            $table->string('origen')->default('excel_trafico');
            $table->string('source_file')->nullable();
            $table->string('source_sheet')->nullable();
            $table->string('source_key')->nullable();
            $table->text('observaciones')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('recibo_chofer_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('recibo_chofer_id');
            $table->string('concepto');
            $table->decimal('cantidad', 14, 3)->nullable();
            $table->string('zona')->nullable();
            $table->decimal('paradas', 14, 3)->nullable();
            $table->decimal('paquetes', 14, 3)->nullable();
            $table->decimal('entregados', 14, 3)->nullable();
            $table->decimal('importe_unitario', 14, 2)->nullable();
            $table->decimal('importe', 14, 2)->default(0);
            $table->string('source_key')->nullable()->unique();
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        Schema::create('planillas_pago_chofer', function (Blueprint $table) {
            $table->id();
            $table->string('numero')->unique();
            $table->date('fecha');
            $table->string('estado')->default('borrador');
            $table->decimal('total', 14, 2)->default(0);
            $table->text('observaciones')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('planilla_pago_chofer_recibos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('planilla_pago_chofer_id');
            $table->unsignedBigInteger('recibo_chofer_id');
            $table->decimal('monto_en_planilla', 14, 2);
            $table->string('estado_pago')->default('sin_resultado');
            $table->unsignedBigInteger('driver_payment_type_id')->nullable();
            $table->timestamps();
        });

        Schema::create('transportista_liquidation_meta', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('transportista_id');
            $table->string('titular')->nullable();
            $table->string('patente')->nullable();
            $table->string('modelo')->nullable();
            $table->string('unidad')->nullable();
            $table->string('plaza')->nullable();
            $table->string('tipo_periodo')->nullable();
            $table->json('extra')->nullable();
            $table->timestamps();
        });

        Schema::create('driver_logistics_records', function (Blueprint $table) {
            $table->id();
            $table->date('fecha');
            $table->unsignedBigInteger('transportista_id');
            $table->unsignedBigInteger('transporte_id')->nullable();
            $table->unsignedBigInteger('traffic_zone_id')->nullable();
            $table->string('svc')->nullable();
            $table->string('ruta')->nullable();
            $table->string('numero')->nullable();
            $table->string('zona')->nullable();
            $table->integer('paradas')->default(0);
            $table->integer('paquetes')->default(0);
            $table->integer('entregados')->default(0);
            $table->decimal('kilometros', 10, 2)->default(0);
            $table->boolean('zona_lejana')->default(false);
            $table->timestamps();
        });

        Schema::create('settlement_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->integer('priority')->default(0);
            $table->boolean('is_modifier')->default(false);
            $table->boolean('active')->default(true);
            $table->string('scope')->default('line');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('settlement_rule_conditions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('settlement_rule_id');
            $table->string('field');
            $table->string('operator');
            $table->json('value')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('settlement_rule_actions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('settlement_rule_id');
            $table->string('action_type');
            $table->json('payload')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('system_parameters', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('label');
            $table->text('description')->nullable();
            $table->string('value')->nullable();
            $table->timestamps();
        });

        Schema::create('transportista_payment_methods', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('transportista_id');
            $table->unsignedBigInteger('bank_id')->nullable();
            $table->string('cbu');
            $table->string('account_number')->nullable();
            $table->string('description')->nullable();
            $table->boolean('is_default')->default(false);
            $table->json('tags')->nullable();
            $table->timestamps();
        });
    }

    private function buildTrafficWorkbook(): UploadedFile
    {
        $spreadsheet = new Spreadsheet();
        $base = $spreadsheet->getActiveSheet();
        $base->setTitle('Base Choferes');
        $base->fromArray(['Chofer', 'Titular', 'Patente', 'Modelo', 'Unidad', 'Plaza'], null, 'A1');
        $base->fromArray(['Juan Perez', 'Titular 1', 'AAA111', 'Modelo X', 'Unidad 1', 'AMBA'], null, 'A2');

        $ops = $spreadsheet->createSheet();
        $ops->setTitle('AMBA am');
        $ops->fromArray(['Fecha', 'Chofer', 'Patente', 'Ruta', 'Numero', 'Entregados', 'Plaza'], null, 'A1');
        $ops->fromArray(['2026-02-10', 'Juan Perez', 'AAA111', 'RUTA 1', '100', '10', 'AMBA'], null, 'A2');

        $path = storage_path('framework/testing/traffic-' . uniqid() . '.xlsx');
        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }

        $writer = new Xlsx($spreadsheet);
        $writer->save($path);

        return new UploadedFile($path, 'traffic-test.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }

    private function buildBankResultWorkbook(int $paidId, int $pendingId): UploadedFile
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray(['recibo_id', 'resultado'], null, 'A1');
        $sheet->fromArray([$paidId, 'PAGADO'], null, 'A2');
        $sheet->fromArray([$pendingId, 'NO_PAGADO'], null, 'A3');

        $path = storage_path('framework/testing/bank-' . uniqid() . '.xlsx');
        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }

        $writer = new Xlsx($spreadsheet);
        $writer->save($path);

        return new UploadedFile($path, 'bank-test.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }

    private function buildConfiguredConceptWorkbook(): UploadedFile
    {
        $spreadsheet = new Spreadsheet();

        $zona = $spreadsheet->getActiveSheet();
        $zona->setTitle('AMBA ZONA');
        $zona->fromArray(['Fecha', 'Chofer', 'Patente', 'Zona', 'Plaza'], null, 'A1');
        $zona->fromArray(['2026-02-10', 'Juan Perez', 'AAA111', 'MEDIO', 'AMBA'], null, 'A2');

        $km = $spreadsheet->createSheet();
        $km->setTitle('AMBA KM');
        $km->fromArray(['Fecha', 'Chofer', 'Patente', 'Kilometros', 'Entregados', 'Plaza'], null, 'A1');
        $km->fromArray(['2026-02-11', 'Juan Perez', 'AAA111', '12', '8', 'AMBA'], null, 'A2');

        $package = $spreadsheet->createSheet();
        $package->setTitle('AMBA PAQ');
        $package->fromArray(['Fecha', 'Chofer', 'Patente', 'Entregados', 'Paquetes', 'Plaza'], null, 'A1');
        $package->fromArray(['2026-02-12', 'Juan Perez', 'AAA111', '5', '5', 'AMBA'], null, 'A2');

        $path = storage_path('framework/testing/traffic-config-' . uniqid() . '.xlsx');
        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }

        $writer = new Xlsx($spreadsheet);
        $writer->save($path);

        return new UploadedFile($path, 'traffic-config-test.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }

    private function buildKmExcessWorkbook(): UploadedFile
    {
        $spreadsheet = new Spreadsheet();

        $km = $spreadsheet->getActiveSheet();
        $km->setTitle('AMBA KM');
        $km->fromArray(['Fecha', 'Chofer', 'Patente', 'Kilometros', 'Entregados', 'Plaza'], null, 'A1');
        $km->fromArray(['2026-02-11', 'Moto Test', 'MOTO123', '12', '35', 'AMBA'], null, 'A2');
        $km->fromArray(['2026-02-11', 'Van Test', 'VAN123', '12', '35', 'AMBA'], null, 'A3');

        $path = storage_path('framework/testing/traffic-km-excess-' . uniqid() . '.xlsx');
        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }

        $writer = new Xlsx($spreadsheet);
        $writer->save($path);

        return new UploadedFile($path, 'traffic-km-excess-test.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }

    private function buildZoneYearWorkbook(): UploadedFile
    {
        $spreadsheet = new Spreadsheet();

        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('CORDOBA');
        $sheet->fromArray(['Fecha', 'Chofer', 'Patente', 'Modelo', 'Zona', 'Plaza'], null, 'A1');
        $sheet->fromArray(['2026-02-11', 'Modelo Test', 'MOD123', '2023', 'ZONA 1', 'CORDOBA'], null, 'A2');

        $path = storage_path('framework/testing/traffic-zone-year-' . uniqid() . '.xlsx');
        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }

        $writer = new Xlsx($spreadsheet);
        $writer->save($path);

        return new UploadedFile($path, 'traffic-zone-year-test.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }

    private function seedFixedRule(string $name, float $amount, array $conditions = [], int $priority = 10, string $scope = 'line', array $extraPayload = []): void
    {
        $rule = \App\Models\SettlementRule::create([
            'name' => $name,
            'priority' => $priority,
            'is_modifier' => false,
            'active' => true,
            'scope' => $scope,
        ]);

        foreach ($conditions as $field => $val) {
            \App\Models\SettlementRuleCondition::create([
                'settlement_rule_id' => $rule->id,
                'field' => $field,
                'operator' => is_array($val) ? 'IN' : '=',
                'value' => $val,
            ]);
        }

        \App\Models\SettlementRuleAction::create([
            'settlement_rule_id' => $rule->id,
            'action_type' => 'FIXED_AMOUNT',
            'payload' => array_merge(['amount' => $amount], $extraPayload),
        ]);

        \App\Services\DriverPayments\RuleEngine\Repositories\CachedEloquentSettlementRuleRepository::flushCache();
    }

    public function test_receipt_filtering_by_item_dates(): void
    {
        $transportista = Transportista::create([
            'name' => 'Chofer Test Filter',
            'is_active' => true,
        ]);

        $recibo1 = ReciboChofer::create([
            'transportista_id' => $transportista->id,
            'tipo_periodo' => 'mensual',
            'periodo_desde' => '2026-02-01',
            'periodo_hasta' => '2026-02-28',
            'fecha_emision' => '2026-03-01',
            'estado' => ReciboChofer::ESTADO_CARGADO,
            'importe_total' => 1000,
        ]);

        \App\Models\ReciboChoferItem::create([
            'recibo_chofer_id' => $recibo1->id,
            'concepto' => 'Viaje 1',
            'cantidad' => 1,
            'importe' => 1000,
            'meta' => ['fecha' => '2026-02-10'],
        ]);

        $recibo2 = ReciboChofer::create([
            'transportista_id' => $transportista->id,
            'tipo_periodo' => 'mensual',
            'periodo_desde' => '2026-02-01',
            'periodo_hasta' => '2026-02-28',
            'fecha_emision' => '2026-03-01',
            'estado' => ReciboChofer::ESTADO_CARGADO,
            'importe_total' => 2000,
        ]);

        \App\Models\ReciboChoferItem::create([
            'recibo_chofer_id' => $recibo2->id,
            'concepto' => 'Viaje 2',
            'cantidad' => 1,
            'importe' => 2000,
            'meta' => ['fecha' => '2026-02-20'],
        ]);

        // Query with date range matching only recibo1
        $query1 = ReciboChofer::query()->whereHas('items', function ($sub) {
            $sub->whereDate('meta->fecha', '>=', '2026-02-05')
                ->whereDate('meta->fecha', '<=', '2026-02-15');
        })->get();

        $this->assertCount(1, $query1);
        $this->assertEquals($recibo1->id, $query1->first()->id);

        // Query with date range matching only recibo2
        $query2 = ReciboChofer::query()->whereHas('items', function ($sub) {
            $sub->whereDate('meta->fecha', '>=', '2026-02-18')
                ->whereDate('meta->fecha', '<=', '2026-02-22');
        })->get();

        $this->assertCount(1, $query2);
        $this->assertEquals($recibo2->id, $query2->first()->id);

        // Query with date range matching both
        $queryAll = ReciboChofer::query()->whereHas('items', function ($sub) {
            $sub->whereDate('meta->fecha', '>=', '2026-02-05')
                ->whereDate('meta->fecha', '<=', '2026-02-25');
        })->get();

        $this->assertCount(2, $queryAll);
    }

    public function test_controller_index_filters_by_item_dates(): void
    {
        // Bypass authorization gates
        \Illuminate\Support\Facades\Gate::before(fn() => true);

        $user = \App\Models\User::create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
        ]);

        $transportista = Transportista::create([
            'name' => 'Chofer Test Ctrl Filter',
            'is_active' => true,
        ]);

        $recibo1 = ReciboChofer::create([
            'transportista_id' => $transportista->id,
            'tipo_periodo' => 'mensual',
            'periodo_desde' => '2026-02-01',
            'periodo_hasta' => '2026-02-28',
            'fecha_emision' => '2026-03-01',
            'estado' => ReciboChofer::ESTADO_CARGADO,
            'importe_total' => 1000,
        ]);

        \App\Models\ReciboChoferItem::create([
            'recibo_chofer_id' => $recibo1->id,
            'concepto' => 'Viaje 1',
            'cantidad' => 1,
            'importe' => 1000,
            'meta' => ['fecha' => '2026-02-10'],
        ]);

        $recibo2 = ReciboChofer::create([
            'transportista_id' => $transportista->id,
            'tipo_periodo' => 'mensual',
            'periodo_desde' => '2026-02-01',
            'periodo_hasta' => '2026-02-28',
            'fecha_emision' => '2026-03-01',
            'estado' => ReciboChofer::ESTADO_CARGADO,
            'importe_total' => 2000,
        ]);

        \App\Models\ReciboChoferItem::create([
            'recibo_chofer_id' => $recibo2->id,
            'concepto' => 'Viaje 2',
            'cantidad' => 1,
            'importe' => 2000,
            'meta' => ['fecha' => '2026-02-20'],
        ]);

        // Request with desde & hasta matching only recibo1
        $response = $this->actingAs($user)->get(route('pago-choferes.recibos.index', [
            'desde' => '2026-02-05',
            'hasta' => '2026-02-15',
        ]));

        $response->assertStatus(200);
        $recibosOnView = $response->viewData('recibos');
        $this->assertCount(1, $recibosOnView);
        $this->assertEquals($recibo1->id, $recibosOnView->first()->id);
    }

    public function test_export_santander_txt(): void
    {
        // Bypass authorization gates
        \Illuminate\Support\Facades\Gate::before(fn() => true);

        // System Parameters setup
        \App\Models\SystemParameter::create([
            'key' => 'company_cuit',
            'label' => 'Company CUIT',
            'value' => '30999999999',
        ]);
        \App\Models\SystemParameter::create([
            'key' => 'santander_agreement_number',
            'label' => 'Agreement Number',
            'value' => '05',
        ]);

        $user = \App\Models\User::create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
        ]);

        $transportista = Transportista::create([
            'name' => 'Chofer Test Ctrl Filter',
            'is_active' => true,
            'tax_id' => '20123456789',
            'cbu' => '0720262188000036854124',
        ]);

        $recibo1 = ReciboChofer::create([
            'transportista_id' => $transportista->id,
            'tipo_periodo' => 'mensual',
            'periodo_desde' => '2026-02-01',
            'periodo_hasta' => '2026-02-28',
            'fecha_emision' => '2026-03-01',
            'estado' => ReciboChofer::ESTADO_EN_PLANILLA,
            'importe_total' => 1250.50,
        ]);

        $planilla = PlanillaPagoChofer::create([
            'numero' => '123',
            'fecha' => '2026-03-05',
            'estado' => PlanillaPagoChofer::ESTADO_CONFIRMADA,
            'total' => 1250.50,
        ]);

        PlanillaPagoChoferRecibo::create([
            'planilla_pago_chofer_id' => $planilla->id,
            'recibo_chofer_id' => $recibo1->id,
            'monto_en_planilla' => 1250.50,
            'estado_pago' => PlanillaPagoChoferRecibo::ESTADO_SIN_RESULTADO,
        ]);

        $response = $this->actingAs($user)->get(route('pago-choferes.planillas.export-santander', $planilla));

        $response->assertStatus(200);
        $content = $response->getContent();

        $this->assertNotEmpty($content);
        $lines = explode("\r\n", rtrim($content, "\r\n"));

        // Header + Detail + Trailer = 3 lines
        $this->assertCount(3, $lines);

        foreach ($lines as $line) {
            $this->assertEquals(650, strlen($line), "Each line must be exactly 650 chars long.");
        }

        // Validate Header (H)
        $this->assertStringStartsWith('H', $lines[0]);
        $this->assertStringContainsString('30999999999', $lines[0]);
        $this->assertStringContainsString('05', $lines[0]);

        // Validate Detail (D)
        $this->assertStringStartsWith('D', $lines[1]);
        $this->assertStringContainsString('20123456789', $lines[1]);
        $this->assertStringContainsString('00720262100088000036854124', $lines[1]);
        $this->assertStringContainsString('000000000125050', $lines[1]);
        $this->assertStringContainsString('000000000202602', $lines[1]);

        // Validate Trailer (T)
        $this->assertStringStartsWith('T', $lines[2]);
        $this->assertStringContainsString('0000001', $lines[2]);
        $this->assertStringContainsString('000000000125050', $lines[2]);
    }

    public function test_export_santander_txt_validation_fails()
    {
        \Illuminate\Support\Facades\Gate::before(fn() => true);

        $user = \App\Models\User::create([
            'name' => 'Admin User',
            'email' => 'admin_val@example.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
        ]);

        \App\Models\SystemParameter::create([
            'key' => 'company_cuit',
            'label' => 'CUIT de la empresa',
            'value' => '30123456789',
        ]);
        \App\Models\SystemParameter::create([
            'key' => 'santander_agreement_number',
            'label' => 'Número de acuerdo',
            'value' => '01',
        ]);

        $transportista = Transportista::create([
            'name' => 'Chofer Invalido',
            'is_active' => true,
            'tax_id' => 'abc',
            'cbu' => '',
        ]);

        $planilla = PlanillaPagoChofer::create([
            'numero' => 'PL-SANT-ERR-001',
            'fecha' => now(),
            'estado' => PlanillaPagoChofer::ESTADO_BORRADOR,
        ]);

        $recibo1 = ReciboChofer::create([
            'transportista_id' => $transportista->id,
            'tipo_periodo' => 'mensual',
            'periodo_desde' => '2026-02-01',
            'periodo_hasta' => '2026-02-28',
            'fecha_emision' => '2026-03-01',
            'estado' => ReciboChofer::ESTADO_EN_PLANILLA,
            'importe_total' => 1250.50,
        ]);

        PlanillaPagoChoferRecibo::create([
            'planilla_pago_chofer_id' => $planilla->id,
            'recibo_chofer_id' => $recibo1->id,
            'monto_en_planilla' => 1250.50,
            'estado_pago' => PlanillaPagoChoferRecibo::ESTADO_SIN_RESULTADO,
        ]);

        $response = $this->actingAs($user)->get(route('pago-choferes.planillas.export-santander', $planilla));

        $response->assertStatus(302);
        $response->assertRedirect(route('pago-choferes.planillas.show', $planilla));
        $response->assertSessionHas('warnings');

        $warnings = session('warnings');
        $this->assertNotEmpty($warnings);
        $this->assertStringContainsString('CUIT/CUIL vacío', $warnings[0]);
        $this->assertStringContainsString('CBU/CVU vacío', $warnings[0]);
    }

    public function test_planillas_index_filters()
    {
        \Illuminate\Support\Facades\Gate::before(fn() => true);

        $user = \App\Models\User::create([
            'name' => 'Admin User',
            'email' => 'admin_filter@example.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
        ]);

        $transportista1 = Transportista::create([
            'name' => 'Transportista A',
            'is_active' => true,
            'tax_id' => '20111111119',
            'cbu' => '0720262188000036854124',
        ]);

        $transportista2 = Transportista::create([
            'name' => 'Transportista B',
            'is_active' => true,
            'tax_id' => '20222222229',
            'cbu' => '0720262188000036854125',
        ]);

        $planilla1 = PlanillaPagoChofer::create([
            'numero' => 'PL-001',
            'fecha' => '2026-07-01',
            'estado' => PlanillaPagoChofer::ESTADO_BORRADOR,
        ]);

        $recibo1 = ReciboChofer::create([
            'transportista_id' => $transportista1->id,
            'tipo_periodo' => 'mensual',
            'periodo_desde' => '2026-02-01',
            'periodo_hasta' => '2026-02-28',
            'fecha_emision' => '2026-03-01',
            'estado' => ReciboChofer::ESTADO_EN_PLANILLA,
            'importe_total' => 1000.00,
        ]);

        PlanillaPagoChoferRecibo::create([
            'planilla_pago_chofer_id' => $planilla1->id,
            'recibo_chofer_id' => $recibo1->id,
            'monto_en_planilla' => 1000.00,
            'estado_pago' => PlanillaPagoChoferRecibo::ESTADO_SIN_RESULTADO,
        ]);

        $planilla2 = PlanillaPagoChofer::create([
            'numero' => 'PL-002',
            'fecha' => '2026-07-15',
            'estado' => PlanillaPagoChofer::ESTADO_CONFIRMADA,
        ]);

        $recibo2 = ReciboChofer::create([
            'transportista_id' => $transportista2->id,
            'tipo_periodo' => 'mensual',
            'periodo_desde' => '2026-02-01',
            'periodo_hasta' => '2026-02-28',
            'fecha_emision' => '2026-03-01',
            'estado' => ReciboChofer::ESTADO_EN_PLANILLA,
            'importe_total' => 2000.00,
        ]);

        PlanillaPagoChoferRecibo::create([
            'planilla_pago_chofer_id' => $planilla2->id,
            'recibo_chofer_id' => $recibo2->id,
            'monto_en_planilla' => 2000.00,
            'estado_pago' => PlanillaPagoChoferRecibo::ESTADO_SIN_RESULTADO,
        ]);

        // Filter by state = borrador
        $response = $this->actingAs($user)->get(route('pago-choferes.planillas.index', ['estado' => 'borrador']));
        $response->assertStatus(200);
        $planillas = $response->viewData('planillas');
        $this->assertTrue($planillas->contains('id', $planilla1->id));
        $this->assertFalse($planillas->contains('id', $planilla2->id));

        // Filter by transportista = Transportista 2
        $response = $this->actingAs($user)->get(route('pago-choferes.planillas.index', ['transportista_id' => $transportista2->id]));
        $response->assertStatus(200);
        $planillas = $response->viewData('planillas');
        $this->assertFalse($planillas->contains('id', $planilla1->id));
        $this->assertTrue($planillas->contains('id', $planilla2->id));

        // Filter by date range (desde/hasta)
        $response = $this->actingAs($user)->get(route('pago-choferes.planillas.index', [
            'desde' => '2026-07-10',
            'hasta' => '2026-07-20',
        ]));
        $response->assertStatus(200);
        $planillas = $response->viewData('planillas');
        $this->assertFalse($planillas->contains('id', $planilla1->id));
        $this->assertTrue($planillas->contains('id', $planilla2->id));
    }

    public function test_planilla_mark_all_paid()
    {
        \Illuminate\Support\Facades\Gate::before(fn() => true);

        $user = \App\Models\User::create([
            'name' => 'Admin User',
            'email' => 'admin_markpaid@example.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
        ]);

        $paidType = \App\Models\DriverPaymentType::create([
            'description' => 'Test Pagado',
            'type' => true,
        ]);

        $transportista = Transportista::create([
            'name' => 'Chofer Test',
            'is_active' => true,
            'tax_id' => '20123456789',
            'cbu' => '0720262188000036854124',
        ]);

        $planilla = PlanillaPagoChofer::create([
            'numero' => 'PL-MARK-PAID',
            'fecha' => now(),
            'estado' => PlanillaPagoChofer::ESTADO_BORRADOR,
        ]);

        $recibo = ReciboChofer::create([
            'transportista_id' => $transportista->id,
            'tipo_periodo' => 'mensual',
            'periodo_desde' => '2026-02-01',
            'periodo_hasta' => '2026-02-28',
            'fecha_emision' => '2026-03-01',
            'estado' => ReciboChofer::ESTADO_EN_PLANILLA,
            'importe_total' => 1250.50,
        ]);

        $link = PlanillaPagoChoferRecibo::create([
            'planilla_pago_chofer_id' => $planilla->id,
            'recibo_chofer_id' => $recibo->id,
            'monto_en_planilla' => 1250.50,
            'estado_pago' => PlanillaPagoChoferRecibo::ESTADO_SIN_RESULTADO,
        ]);

        $response = $this->actingAs($user)->post(route('pago-choferes.planillas.todos-pagados', $planilla));

        $response->assertStatus(302);
        $response->assertRedirect(route('pago-choferes.planillas.show', $planilla));

        $link->refresh();
        $this->assertEquals(PlanillaPagoChoferRecibo::ESTADO_PAGADO, $link->estado_pago);
        $this->assertEquals($paidType->id, $link->driver_payment_type_id);

        $recibo->refresh();
        $this->assertEquals(ReciboChofer::ESTADO_PAGADO, $recibo->estado);

        $planilla->refresh();
        $this->assertEquals(PlanillaPagoChofer::ESTADO_CERRADA, $planilla->estado);
    }

    public function test_planilla_mark_all_paid_without_closing()
    {
        \Illuminate\Support\Facades\Gate::before(fn() => true);

        $user = \App\Models\User::create([
            'name' => 'Admin User',
            'email' => 'admin_markpaid_open@example.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
        ]);

        $paidType = \App\Models\DriverPaymentType::create([
            'description' => 'Test Pagado',
            'type' => true,
        ]);

        $transportista = Transportista::create([
            'name' => 'Chofer Test',
            'is_active' => true,
            'tax_id' => '20123456789',
            'cbu' => '0720262188000036854124',
        ]);

        $planilla = PlanillaPagoChofer::create([
            'numero' => 'PL-MARK-PAID-OPEN',
            'fecha' => now(),
            'estado' => PlanillaPagoChofer::ESTADO_BORRADOR,
        ]);

        $recibo = ReciboChofer::create([
            'transportista_id' => $transportista->id,
            'tipo_periodo' => 'mensual',
            'periodo_desde' => '2026-02-01',
            'periodo_hasta' => '2026-02-28',
            'fecha_emision' => '2026-03-01',
            'estado' => ReciboChofer::ESTADO_EN_PLANILLA,
            'importe_total' => 1250.50,
        ]);

        $link = PlanillaPagoChoferRecibo::create([
            'planilla_pago_chofer_id' => $planilla->id,
            'recibo_chofer_id' => $recibo->id,
            'monto_en_planilla' => 1250.50,
            'estado_pago' => PlanillaPagoChoferRecibo::ESTADO_SIN_RESULTADO,
        ]);

        $response = $this->actingAs($user)->post(route('pago-choferes.planillas.todos-pagados', $planilla), [
            'close' => '0',
        ]);

        $response->assertStatus(302);
        $response->assertRedirect(route('pago-choferes.planillas.show', $planilla));

        $link->refresh();
        $this->assertEquals(PlanillaPagoChoferRecibo::ESTADO_PAGADO, $link->estado_pago);

        $planilla->refresh();
        $this->assertEquals(PlanillaPagoChofer::ESTADO_PAGADA_PARCIAL, $planilla->estado);
    }

    public function test_planilla_close_manually()
    {
        \Illuminate\Support\Facades\Gate::before(fn() => true);

        $user = \App\Models\User::create([
            'name' => 'Admin User',
            'email' => 'admin_closemanual@example.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
        ]);

        $planilla = PlanillaPagoChofer::create([
            'numero' => 'PL-CLOSE-MANUAL',
            'fecha' => now(),
            'estado' => PlanillaPagoChofer::ESTADO_CONFIRMADA,
        ]);

        $response = $this->actingAs($user)->post(route('pago-choferes.planillas.close', $planilla));

        $response->assertStatus(302);
        $response->assertRedirect(route('pago-choferes.planillas.show', $planilla));

        $planilla->refresh();
        $this->assertEquals(PlanillaPagoChofer::ESTADO_CERRADA, $planilla->estado);
    }

    public function test_export_santander_sandbox_forbidden_for_non_admin()
    {
        $user = \App\Models\User::create([
            'name' => 'Regular User',
            'email' => 'regular@example.com',
            'password' => bcrypt('password'),
            'role' => 'user',
        ]);

        $response = $this->actingAs($user)->post(route('pago-choferes.planillas.export-santander-sandbox'), [
            'company_cuit' => '30123456789',
            'agreement_number' => '01',
            'beneficiary_id' => '999',
            'period' => '202607',
            'payment_date' => '2026-07-31',
            'beneficiary_name' => 'HIJA DUENO',
            'beneficiary_cuit' => '27123456789',
            'beneficiary_cbu' => '0720262188000036854124',
            'amount' => '100.00',
        ]);

        $response->assertStatus(403);
    }

    public function test_export_santander_sandbox_success_for_admin()
    {
        $user = \App\Models\User::create([
            'name' => 'Admin User',
            'email' => 'admin_sandbox@example.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
        ]);

        $response = $this->actingAs($user)->post(route('pago-choferes.planillas.export-santander-sandbox'), [
            'company_cuit' => '30123456789',
            'agreement_number' => '01',
            'beneficiary_id' => '999',
            'period' => '202607',
            'payment_date' => '2026-07-31',
            'beneficiary_name' => 'EjemploO',
            'beneficiary_cuit' => '27123456789',
            'beneficiary_cbu' => '0720262188000036854124',
            'amount' => '125.50',
        ]);

        $response->assertStatus(200);
        $content = $response->getContent();
        $this->assertNotEmpty($content);

        $lines = explode("\r\n", rtrim($content, "\r\n"));
        $this->assertCount(3, $lines);

        // Header
        $this->assertEquals(650, strlen($lines[0]));
        $this->assertStringStartsWith('H30123456789001301', $lines[0]);

        // Detail
        $this->assertEquals(650, strlen($lines[1]));
        $this->assertStringStartsWith('D', $lines[1]);
        $this->assertStringContainsString('EJEMPLOO', $lines[1]);
        $this->assertStringContainsString('00720262100088000036854124', $lines[1]);
        $this->assertStringContainsString('000000000012550', $lines[1]);

        // Trailer
        $this->assertEquals(650, strlen($lines[2]));
        $this->assertStringStartsWith('T', $lines[2]);
        $this->assertStringContainsString('000000000012550', $lines[2]);
    }

    public function test_import_from_logistics_records_uses_transportista_liquidation_meta_plaza_and_zone(): void
    {
        DriverLiquidationSetting::create([
            'tipo_periodo' => 'mensual',
            'anio' => 2026,
            'mes' => 9,
            'desde' => '2026-09-01',
            'hasta' => '2026-09-30',
            'activo' => true,
        ]);

        $zone = TrafficZone::create(['name' => 'DON TORCUATO']);

        DriverPaymentZoneSetting::create([
            'traffic_zone_id' => $zone->id,
            'calc_type' => 'zona',
            'package_rate' => 150.0,
        ]);

        $transportista = Transportista::create([
            'name' => 'Gonzalo Muñoz',
            'is_active' => true,
        ]);

        \App\Models\TransportistaLiquidationMeta::create([
            'transportista_id' => $transportista->id,
            'plaza' => 'DON TORCUATO',
            'tipo_periodo' => 'mensual',
        ]);

        $logisticsRecord = \App\Models\DriverLogisticsRecord::create([
            'fecha' => '2026-09-02',
            'transportista_id' => $transportista->id,
            'svc' => null,
            'zona' => null,
            'paradas' => 10,
            'paquetes' => 20,
            'entregados' => 20,
        ]);

        $run = \App\Models\DriverImportRun::create([
            'source_file' => 'Logística Test',
            'tipo_periodo' => 'mensual',
            'summary' => ['is_logistics' => true],
            'rows_processed' => 0,
            'rows_created' => 0,
            'rows_updated' => 0,
            'rows_with_errors' => 0,
        ]);

        $importer = app(DriverExcelImporter::class);
        $result = $importer->importFromLogisticsRecords($run, collect([$logisticsRecord]), 'ambas');

        $this->assertEquals(1, $result['created']);

        $recibo = ReciboChofer::where('transportista_id', $transportista->id)->first();
        $this->assertNotNull($recibo);
        $this->assertEquals('DON TORCUATO', $recibo->plaza);
        $this->assertEquals('2026-09-02', $recibo->fecha_emision->toDateString());

        $item = $recibo->items()->first();
        $this->assertNotNull($item);
        $this->assertEquals('DON TORCUATO', $item->zona);
        $this->assertEquals($zone->id, $item->meta['traffic_zone_id'] ?? null);
    }

    public function test_import_from_logistics_records_replace_mode_replaces_old_items(): void
    {
        $transportista = Transportista::create([
            'name' => 'Chofer Test Replace',
            'code' => 'CTR123',
            'is_active' => true,
        ]);
        $transporte1 = \App\Models\Transporte::create([
            'transportista_id' => $transportista->id,
            'license_plate' => 'AA111AA',
            'type' => 'camioneta_grande',
            'is_active' => true,
        ]);
        $transporte2 = \App\Models\Transporte::create([
            'transportista_id' => $transportista->id,
            'license_plate' => 'BB222BB',
            'type' => 'camioneta',
            'is_active' => true,
        ]);

        $logisticsRecord = \App\Models\DriverLogisticsRecord::create([
            'fecha' => '2026-09-01',
            'transportista_id' => $transportista->id,
            'transporte_id' => $transporte1->id,
            'svc' => 'CABA',
            'paradas' => 5,
            'paquetes' => 5,
            'entregados' => 5,
        ]);

        $run1 = \App\Models\DriverImportRun::create([
            'source_file' => 'Logística Run 1',
            'tipo_periodo' => 'mensual',
            'summary' => ['is_logistics' => true],
            'rows_processed' => 0,
            'rows_created' => 0,
            'rows_updated' => 0,
            'rows_with_errors' => 0,
        ]);

        $importer = app(DriverExcelImporter::class);
        $importer->importFromLogisticsRecords($run1, collect([$logisticsRecord]), 'ambas', 'replace');

        $recibo = ReciboChofer::where('transportista_id', $transportista->id)->first();
        $this->assertNotNull($recibo);
        $this->assertCount(1, $recibo->items);
        $this->assertEquals('camioneta_grande', $recibo->items->first()->meta['vehicle_type']);

        // Update logistics record with vehicle 2
        $logisticsRecord->update(['transporte_id' => $transporte2->id]);
        $logisticsRecord->refresh()->load(['transportista.liquidationMeta', 'transporte', 'trafficZone']);

        $run2 = \App\Models\DriverImportRun::create([
            'source_file' => 'Logística Run 2',
            'tipo_periodo' => 'mensual',
            'summary' => ['is_logistics' => true],
            'rows_processed' => 0,
            'rows_created' => 0,
            'rows_updated' => 0,
            'rows_with_errors' => 0,
        ]);

        $result2 = $importer->importFromLogisticsRecords($run2, collect([$logisticsRecord]), 'ambas', 'replace');
        $this->assertEquals(1, $result2['created']);
        $recibo = ReciboChofer::where('transportista_id', $transportista->id)->first();
        $this->assertNotNull($recibo);
        $items = $recibo->items()->get();
        $this->assertCount(1, $items);
        $this->assertEquals('camioneta', $items->first()->meta['vehicle_type']);
    }
}
