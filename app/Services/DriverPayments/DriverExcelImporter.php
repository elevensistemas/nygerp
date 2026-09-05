<?php

namespace App\Services\DriverPayments;

use App\Models\DriverImportRun;
use App\Models\DriverImportRunRow;
use App\Models\DriverImportSetting;
use App\Models\DriverPaymentAdjustmentRule;
use App\Models\DriverPaymentConcept;
use App\Models\DriverPaymentKmRange;
use App\Models\DriverPaymentZoneConceptYearValue;
use App\Models\DriverPaymentZoneSetting;
use App\Models\DriverImportVehicleMap;
use App\Models\ReciboChofer;
use App\Models\ReciboChoferItem;
use App\Models\DriverPaymentZoneConcept;
use App\Models\TrafficZone;
use App\Models\Transporte;
use App\Models\Transportista;
use App\Models\TransportistaLiquidationMeta;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use App\Services\DriverPayments\RuleEngine\SettlementRuleEngine;
use App\Services\DriverPayments\RuleEngine\DTOs\SettlementContext;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class DriverExcelImporter
{
    private DriverLiquidationPeriodResolver $periodResolver;
    private SettlementRuleEngine $ruleEngine;
    private ReceiptScopedRuleSynchronizer $receiptScopedRuleSynchronizer;
    private ?string $defaultPeriodType = 'mensual';
    private array $receiptsCache = [];
    private array $itemsCache = [];
    private array $vehicleContextCache = [];
    private array $metaSynced = [];
    private array $fleetContextCache = [];

    private array $vehicleMapsCache = [];
    private ?array $aliasesCache = null;
    private ?array $zoneCalculationConfigIndex = null;
    private bool $packageRateDefaultResolved = false;
    private ?float $packageRateDefaultCache = null;
    private ?array $kmRangesByZone = null;
    private ?array $configuredConceptsIndex = null;
    private ?array $zoneConceptYearValuesIndex = null;

    public function __construct(
        DriverLiquidationPeriodResolver $periodResolver,
        SettlementRuleEngine $ruleEngine,
        ReceiptScopedRuleSynchronizer $receiptScopedRuleSynchronizer
    )
    {
        $this->periodResolver = $periodResolver;
        $this->ruleEngine = $ruleEngine;
        $this->receiptScopedRuleSynchronizer = $receiptScopedRuleSynchronizer;
    }

    public function import(UploadedFile $file, ?User $user = null, array $options = []): DriverImportRun
    {
        $this->assertRequiredTables();
        
        $defaultConfig = DriverImportSetting::where('setting_key', 'driver_payment_default_period_type')->first();
        $this->defaultPeriodType = is_string(optional($defaultConfig)->setting_value) ? $defaultConfig->setting_value : 'mensual';
        $this->receiptsCache = [];
        $this->itemsCache = [];
        $this->vehicleContextCache = [];
        $this->metaSynced = [];
        $this->fleetContextCache = [];
        $this->vehicleMapsCache = DriverImportVehicleMap::all()
            ->mapWithKeys(function (DriverImportVehicleMap $map) {
                return [Str::lower(Str::ascii(trim((string) $map->excel_value))) => $map->vehicle_type];
            })
            ->all();
        $this->aliasesCache = null;
        $this->zoneCalculationConfigIndex = null;
        $this->packageRateDefaultResolved = false;
        $this->packageRateDefaultCache = null;
        $this->kmRangesByZone = null;
        $this->configuredConceptsIndex = null;
        $this->zoneConceptYearValuesIndex = null;

        $run = DriverImportRun::create([
            'source_file' => $file->getClientOriginalName(),
            'tipo_periodo' => 'mixto',
            'created_by' => $user ? $user->id : null,
            'summary' => [],
        ]);

        $reader = IOFactory::createReaderForFile($file->getRealPath());
        $reader->setReadDataOnly(true);
        $workbook = $reader->load($file->getRealPath());
        $sheetNames = $workbook->getSheetNames();

        $transportistasIndex = [];

        $created = 0;
        $updated = 0;
        $processed = 0;
        $errors = 0;
        $touchedReceipts = [];

        foreach ($sheetNames as $sheetName) {
            if ($this->isIgnoredSheet($sheetName)) {
                continue;
            }

            $sheet = $workbook->getSheetByName($sheetName);
            if (! $sheet) {
                continue;
            }

            $headersData = $this->resolveHeaderMap($sheet);
            $headerRow = $headersData['header_row'];
            $headerMap = $headersData['map'];

            if (empty($headerMap)) {
                $this->logRow($run, $sheetName, null, 'error', 'No se detectaron encabezados para importar.');
                $errors++;
                continue;
            }

            $highestRow = $sheet->getHighestDataRow();
            for ($rowNumber = $headerRow + 1; $rowNumber <= $highestRow; $rowNumber++) {
                $processed++;
                $rowAssoc = $this->extractRowAssoc($sheet, $rowNumber, $headerMap);

                if ($this->isOperationalRowEmpty($rowAssoc)) {
                    continue;
                }

                $excelConceptoCheck = mb_strtolower(trim((string) ($this->valueByAliases($rowAssoc, ['zona']) ?? '')));
                if ($excelConceptoCheck === 'no se paga') {
                    $processed--;
                    continue;
                }

                try {
                    $rawFecha = $this->valueByAliases($rowAssoc, ['fecha']);
                    if ($rawFecha === null || trim((string) $rawFecha) === '') {
                        // Fallback hard requirement: fecha always comes in column A.
                        $rawFecha = $this->readCellValue($sheet, 'A', $rowNumber);
                    }

                    $fecha = $this->parseDate($rawFecha);
                    if (! $fecha) {
                        throw new \RuntimeException('Fecha invalida.');
                    }

                    $chofer = trim((string) $this->valueByAliases($rowAssoc, ['chofer']));
                    if ($chofer === '') {
                        throw new \RuntimeException('Chofer vacio.');
                    }

                    $patente = trim((string) $this->valueByAliases($rowAssoc, ['patente']));
                    $transportista = $this->resolveTransportista($chofer, $patente, $rowAssoc, $transportistasIndex);
                    if (! $transportista) {
                        throw new \RuntimeException('No se pudo resolver transportista.');
                    }

                    $vehicleContext = $this->resolveVehicleContext($transportista, $patente);
                    $vehicleType = $this->resolveVehicleTypeFromRow($rowAssoc, $vehicleContext['vehicle_type']);

                    $tipoPeriodo = $this->resolveTransportistaPeriodType($transportista, $rowAssoc);
                    if ($tipoPeriodo === null) {
                        throw new \RuntimeException('El transportista no tiene tipo de liquidacion configurado.');
                    }

                    $periodo = $this->periodResolver->resolveForDate($fecha, $tipoPeriodo);
                    
                    $quincenaOption = $options['quincena_option'] ?? 'ambas';
                    if ($quincenaOption !== 'ambas' && isset($periodo['quincena'])) {
                        if ((int) $periodo['quincena'] !== (int) $quincenaOption) {
                            $processed--; // Don't count skipped rows as processed
                            continue;
                        }
                    }

                    $fleetContext = $this->resolveFleetContext($transportista);
                    $receiptOwnerId = (int) ($fleetContext['billing_transportista_id'] ?? $transportista->id);
                    $receiptFleetId = $fleetContext['fleet_id'] ?? null;
                    $receiptCacheKey = sha1(($receiptFleetId ?? 'driver:' . $receiptOwnerId) . '|' . $tipoPeriodo . '|' . $periodo['desde']->toDateString() . '|' . $periodo['hasta']->toDateString());
                    if (isset($this->receiptsCache[$receiptCacheKey])) {
                        $recibo = $this->receiptsCache[$receiptCacheKey];
                    } else {
                        $reciboQuery = ReciboChofer::query()
                            ->where('transportista_id', $receiptOwnerId)
                            ->where('tipo_periodo', $tipoPeriodo)
                            ->whereDate('periodo_desde', $periodo['desde']->toDateString())
                            ->whereDate('periodo_hasta', $periodo['hasta']->toDateString());

                        if ($receiptFleetId === null) {
                            $reciboQuery->whereNull('driver_payment_fleet_id');
                        } else {
                            $reciboQuery->where('driver_payment_fleet_id', $receiptFleetId);
                        }

                        $recibo = $reciboQuery->first();

                        if (! $recibo) {
                            $recibo = ReciboChofer::create([
                                'transportista_id' => $receiptOwnerId,
                                'driver_payment_fleet_id' => $receiptFleetId,
                                'tipo_periodo' => $tipoPeriodo,
                                'periodo_desde' => $periodo['desde']->toDateString(),
                                'periodo_hasta' => $periodo['hasta']->toDateString(),
                                'fecha_emision' => $fecha->toDateString(),
                                'plaza' => $this->resolveReceiptPlaza($rowAssoc, $transportista, $sheetName),
                                'estado' => ReciboChofer::ESTADO_CARGADO,
                                'origen' => 'excel_trafico',
                                'source_file' => $file->getClientOriginalName(),
                                'source_sheet' => $sheetName,
                                'source_key' => $receiptCacheKey,
                                'created_by' => $user ? $user->id : null,
                                'updated_by' => $user ? $user->id : null,
                            ]);
                        }
                        $this->receiptsCache[$receiptCacheKey] = $recibo;
                    }

                    $ruta = trim((string) $this->valueByAliases($rowAssoc, ['ruta']));
                    $numero = trim((string) $this->valueByAliases($rowAssoc, ['numero', 'nro']));
                    $excelConcepto = trim((string) ($this->valueByAliases($rowAssoc, ['zona']) ?? ''));
                    $zonaHoja = $sheetName;
                    $paradas = $this->parseDecimal($this->valueByAliases($rowAssoc, ['paradas']));
                    $paquetes = $this->parseDecimal($this->valueByAliases($rowAssoc, ['paquetes']));
                    $entregados = $this->parseDecimal($this->valueByAliases($rowAssoc, ['entregados']));
                    if ($entregados === null) {
                        $entregados = $paquetes;
                    }
                    $paquetesAusentes = $this->parseDecimal($this->valueByAliases($rowAssoc, ['paquete ausente', 'paquetes ausentes', 'ausentes']));
                    $kilometros = $this->resolveKilometersValue($rowAssoc);
                    $zonaLejana = $this->parseBooleanish($this->valueByAliases($rowAssoc, ['zona lejana']));
                    $modelYear = $this->parseModelYear($this->valueByAliases($rowAssoc, ['modelo']));
                    $cantidad = $this->parseDecimal($this->valueByAliases($rowAssoc, ['entregados', 'paradas'])) ?? 1.0;
                    $zoneCalc = $this->resolveZoneCalculationConfig([$excelConcepto, $zonaHoja]);
                    $zonaId = $zoneCalc['zone_id'];
                    $calcType = $zoneCalc['calc_type'];

                    $sourceKey = $this->buildItemSourceKey(
                        $fecha,
                        $calcType,
                        $sheetName,
                        $rowAssoc,
                        [
                            'ruta' => $ruta,
                            'numero' => $numero,
                            'zona' => $excelConcepto,
                            'paradas' => $paradas,
                            'paquetes' => $paquetes,
                            'entregados' => $entregados,
                            'paquetes_ausentes' => $paquetesAusentes,
                            'kilometros' => $kilometros,
                            'vehicle_type' => $vehicleType,
                            'zona_lejana' => $zonaLejana ? 1 : 0,
                        ]
                    );

                    $concepto = $excelConcepto !== '' ? $excelConcepto : 'Viaje';
                    if ($excelConcepto === '' && ($ruta !== '' || $numero !== '')) {
                        $concepto = trim('Ruta ' . $ruta . ' #' . $numero);
                    }

                    $context = new SettlementContext(
                        $fecha,
                        (int) $transportista->id,
                        trim((string) $excelConcepto) !== '' ? trim((string) $excelConcepto) : null,
                        $zonaId,
                        $vehicleType,
                        $modelYear,
                        (float) $kilometros,
                        (float) ($entregados ?? $paquetes ?? 0),
                        (float) $paquetesAusentes,
                        (float) $paradas,
                        $zonaLejana
                    );

                    $breakdown = $this->ruleEngine->calculate($context);

                    if (!isset($this->itemsCache[$recibo->id])) {
                        $this->itemsCache[$recibo->id] = ReciboChoferItem::where('recibo_chofer_id', $recibo->id)
                            ->get()
                            ->keyBy('source_key')
                            ->all();
                    }

                    $existingItem = $this->itemsCache[$recibo->id][$sourceKey] ?? null;
                    
                    // Priority is the calculated Total Amount
                    [$importeUnitario, $importe] = $this->applyConceptSign($concepto, $breakdown->baseAmount, $breakdown->totalAmount);

                    $itemPayload = [
                        'recibo_chofer_id' => $recibo->id,
                        'concepto' => $concepto,
                        'cantidad' => $cantidad,
                        'zona' => $zoneCalc['zone_name'] ?? trim((string) $zonaHoja),
                        'paradas' => $paradas,
                        'paquetes' => $paquetes,
                        'entregados' => $entregados,
                        'importe_unitario' => $importeUnitario,
                        'importe' => $importe,
                        'meta' => array_merge(
                            $this->buildItemMeta($rowAssoc, $fecha, $sheetName, $rowNumber),
                            [
                                'calc_type' => $calcType,
                                'traffic_zone_id' => $zonaId,
                                'vehicle_type' => $vehicleType,
                                'transporte_id' => $vehicleContext['transporte_id'],
                                'model_year' => $modelYear,
                                'source_transportista_id' => $transportista->id,
                                'source_transportista_name' => $transportista->name,
                                'zona_lejana' => $zonaLejana,
                                'paquetes_ausentes' => $paquetesAusentes,
                                'kilometros_source' => $kilometros,
                                'receipt_owner_transportista_id' => $receiptOwnerId,
                                'driver_payment_fleet_id' => $receiptFleetId,
                                'pricing_breakdown' => $breakdown->toArray(),
                            ]
                        ),
                    ];

                    if ($existingItem) {
                        $existingItem->fill($itemPayload);
                        $existingItem->save();
                        $updated++;
                    } else {
                        try {
                            $newItem = ReciboChoferItem::create(array_merge($itemPayload, [
                                'source_key' => $sourceKey,
                            ]));
                            $this->itemsCache[$recibo->id][$sourceKey] = $newItem;
                            $created++;
                        } catch (\Illuminate\Database\QueryException $e) {
                            if ($e->errorInfo[1] == 1062 || $e->getCode() == 23000) {
                                $orphanedItem = ReciboChoferItem::where('source_key', $sourceKey)->first();
                                if ($orphanedItem) {
                                    $orphanedItem->fill($itemPayload);
                                    $orphanedItem->save();
                                    $this->itemsCache[$recibo->id][$sourceKey] = $orphanedItem;
                                    $updated++;
                                } else {
                                    throw $e;
                                }
                            } else {
                                throw $e;
                            }
                        }
                    }

                    $touchedReceipts[$recibo->id] = $recibo->id;
                } catch (\Throwable $e) {
                    $errors++;
                    $this->logRow($run, $sheetName, $rowNumber, 'error', $this->shortErrorMessage($e), $rowAssoc);
                }
            }
        }

        foreach (array_values($touchedReceipts) as $reciboId) {
            $recibo = ReciboChofer::find($reciboId);
            if ($recibo) {
                $this->receiptScopedRuleSynchronizer->sync($recibo);
                $this->syncAutomaticAdjustmentsForReceipt($recibo);
                $recibo->recalculateTotal();
            }
        }

        $run->update([
            'rows_processed' => $processed,
            'rows_created' => $created,
            'rows_updated' => $updated,
            'rows_with_errors' => $errors,
            'summary' => [
                'sheets' => $sheetNames,
                'touched_receipts' => count($touchedReceipts),
                'base_choferes_loaded' => false,
            ],
        ]);

        // Avoid eager-loading every row log after import because large files can
        // trigger request timeouts while casting thousands of JSON payloads.
        return $run->fresh();
    }

    public function prepareImport(string $tempPath, string $originalFilename, ?User $user = null, array $options = []): array
    {
        $this->assertRequiredTables();
        
        $filePath = \Illuminate\Support\Facades\Storage::disk('local')->path($tempPath);

        $reader = IOFactory::createReaderForFile($filePath);
        $reader->setReadDataOnly(true);
        $workbook = $reader->load($filePath);
        $sheetNames = $workbook->getSheetNames();

        $chunks = [];
        foreach ($sheetNames as $sheetName) {
            if ($this->isIgnoredSheet($sheetName)) {
                continue;
            }

            $sheet = $workbook->getSheetByName($sheetName);
            if (! $sheet) {
                continue;
            }

            $headersData = $this->resolveHeaderMap($sheet);
            $headerRow = $headersData['header_row'];
            $headerMap = $headersData['map'];

            if (empty($headerMap)) {
                continue;
            }

            $highestRow = $sheet->getHighestDataRow();
            
            // Create chunks of 150 rows
            $chunkSize = 150;
            for ($startRow = $headerRow + 1; $startRow <= $highestRow; $startRow += $chunkSize) {
                $endRow = min($startRow + $chunkSize - 1, $highestRow);
                $chunks[] = [
                    'sheet_name' => $sheetName,
                    'start_row' => $startRow,
                    'end_row' => $endRow,
                ];
            }
        }

        $run = DriverImportRun::create([
            'source_file' => $originalFilename,
            'tipo_periodo' => 'mixto',
            'created_by' => $user ? $user->id : null,
            'summary' => [
                'temp_filepath' => $tempPath,
                'quincena_option' => $options['quincena_option'] ?? 'ambas',
                'touched_receipts' => [],
            ],
            'rows_processed' => 0,
            'rows_created' => 0,
            'rows_updated' => 0,
            'rows_with_errors' => 0,
        ]);

        return [
            'run' => $run,
            'chunks' => $chunks,
        ];
    }

    public function importChunk(DriverImportRun $run, array $chunk): array
    {
        $this->assertRequiredTables();
        
        $defaultConfig = DriverImportSetting::where('setting_key', 'driver_payment_default_period_type')->first();
        $this->defaultPeriodType = is_string(optional($defaultConfig)->setting_value) ? $defaultConfig->setting_value : 'mensual';
        $this->receiptsCache = [];
        $this->itemsCache = [];
        $this->vehicleContextCache = [];
        $this->metaSynced = [];
        $this->fleetContextCache = [];
        $this->vehicleMapsCache = DriverImportVehicleMap::all()
            ->mapWithKeys(function (DriverImportVehicleMap $map) {
                return [Str::lower(Str::ascii(trim((string) $map->excel_value))) => $map->vehicle_type];
            })
            ->all();
        $this->aliasesCache = null;
        $this->zoneCalculationConfigIndex = null;
        $this->packageRateDefaultResolved = false;
        $this->packageRateDefaultCache = null;
        $this->kmRangesByZone = null;
        $this->configuredConceptsIndex = null;
        $this->zoneConceptYearValuesIndex = null;

        $summary = $run->summary;
        $quincenaOption = $summary['quincena_option'] ?? 'ambas';

        $rowsData = $chunk['rows'] ?? [];
        if (empty($rowsData)) {
            return ['processed' => 0, 'created' => 0, 'updated' => 0, 'errors' => 0];
        }

        $headerRowData = $rowsData[0] ?? [];
        $headerMap = [];
        foreach ($headerRowData as $colIndex => $value) {
            $label = $this->normalizeHeader((string) $value);
            if ($label === '') {
                continue;
            }
            $headerMap[$label] = $colIndex;
        }

        if (empty($headerMap)) {
            $this->logRow($run, $chunk['sheet_name'], null, 'error', 'No se detectaron encabezados para importar.');
            $run->increment('rows_with_errors');
            return ['processed' => 0, 'created' => 0, 'updated' => 0, 'errors' => 1];
        }

        $transportistasIndex = [];
        $created = 0;
        $updated = 0;
        $processed = 0;
        $errors = 0;
        $touchedReceipts = [];

        $startRow = (int) $chunk['start_row'];
        $dataRowsCount = count($rowsData) - 1;

        DB::transaction(function () use (
            $rowsData, $dataRowsCount, $startRow, $headerMap, $quincenaOption, $run, $chunk,
            &$processed, &$created, &$updated, &$errors, &$touchedReceipts, &$transportistasIndex
        ) {
            for ($i = 1; $i <= $dataRowsCount; $i++) {
                $rowNumber = $startRow + $i - 1;
                $rowArray = $rowsData[$i] ?? [];
                
                $processed++;
                
                $rowAssoc = [];
                foreach ($headerMap as $header => $colIndex) {
                    $rowAssoc[$header] = $rowArray[$colIndex] ?? null;
                }

                if ($this->isOperationalRowEmpty($rowAssoc)) {
                    continue;
                }

                $excelConceptoCheck = mb_strtolower(trim((string) ($this->valueByAliases($rowAssoc, ['zona']) ?? '')));
                if ($excelConceptoCheck === 'no se paga') {
                    $processed--;
                    continue;
                }

                try {
                    $rawFecha = $this->valueByAliases($rowAssoc, ['fecha']);
                    if ($rawFecha === null || trim((string) $rawFecha) === '') {
                        $rawFecha = $rowArray[0] ?? null;
                    }

                    $fecha = $this->parseDate($rawFecha);
                    if (! $fecha) {
                        throw new \RuntimeException('Fecha inválida.');
                    }

                    $chofer = trim((string) $this->valueByAliases($rowAssoc, ['chofer']));
                    if ($chofer === '') {
                        throw new \RuntimeException('Chofer vacío.');
                    }

                    $patente = trim((string) $this->valueByAliases($rowAssoc, ['patente']));
                    $transportista = $this->resolveTransportista($chofer, $patente, $rowAssoc, $transportistasIndex);
                    if (! $transportista) {
                        throw new \RuntimeException('No se pudo resolver transportista.');
                    }

                    $vehicleContext = $this->resolveVehicleContext($transportista, $patente);
                    $vehicleType = $this->resolveVehicleTypeFromRow($rowAssoc, $vehicleContext['vehicle_type']);

                    $tipoPeriodo = $this->resolveTransportistaPeriodType($transportista, $rowAssoc);
                    if ($tipoPeriodo === null) {
                        throw new \RuntimeException('El transportista no tiene tipo de liquidación configurado.');
                    }

                    $periodo = $this->periodResolver->resolveForDate($fecha, $tipoPeriodo);
                    
                    if ($quincenaOption !== 'ambas' && isset($periodo['quincena'])) {
                        if ((int) $periodo['quincena'] !== (int) $quincenaOption) {
                            $processed--; // Don't count skipped rows as processed
                            continue;
                        }
                    }

                    $fleetContext = $this->resolveFleetContext($transportista);
                    $receiptOwnerId = (int) ($fleetContext['billing_transportista_id'] ?? $transportista->id);
                    $receiptFleetId = $fleetContext['fleet_id'] ?? null;
                    $receiptCacheKey = sha1(($receiptFleetId ?? 'driver:' . $receiptOwnerId) . '|' . $tipoPeriodo . '|' . $periodo['desde']->toDateString() . '|' . $periodo['hasta']->toDateString());
                    if (isset($this->receiptsCache[$receiptCacheKey])) {
                        $recibo = $this->receiptsCache[$receiptCacheKey];
                    } else {
                        $reciboQuery = ReciboChofer::query()
                            ->where('transportista_id', $receiptOwnerId)
                            ->where('tipo_periodo', $tipoPeriodo)
                            ->whereDate('periodo_desde', $periodo['desde']->toDateString())
                            ->whereDate('periodo_hasta', $periodo['hasta']->toDateString());

                        if ($receiptFleetId === null) {
                            $reciboQuery->whereNull('driver_payment_fleet_id');
                        } else {
                            $reciboQuery->where('driver_payment_fleet_id', $receiptFleetId);
                        }

                        $recibo = $reciboQuery->first();

                        if (! $recibo) {
                            $recibo = ReciboChofer::create([
                                'transportista_id' => $receiptOwnerId,
                                'driver_payment_fleet_id' => $receiptFleetId,
                                'tipo_periodo' => $tipoPeriodo,
                                'periodo_desde' => $periodo['desde']->toDateString(),
                                'periodo_hasta' => $periodo['hasta']->toDateString(),
                                'fecha_emision' => $fecha->toDateString(),
                                'plaza' => $this->resolveReceiptPlaza($rowAssoc, $transportista, $chunk['sheet_name']),
                                'estado' => ReciboChofer::ESTADO_CARGADO,
                                'origen' => 'excel_trafico',
                                'source_file' => $run->source_file,
                                'source_sheet' => $chunk['sheet_name'],
                                'source_key' => $receiptCacheKey,
                                'created_by' => $run->created_by,
                                'updated_by' => $run->created_by,
                            ]);
                        }
                        $this->receiptsCache[$receiptCacheKey] = $recibo;
                    }

                    $ruta = trim((string) $this->valueByAliases($rowAssoc, ['ruta']));
                    $numero = trim((string) $this->valueByAliases($rowAssoc, ['numero', 'nro']));
                    $excelConcepto = trim((string) ($this->valueByAliases($rowAssoc, ['zona']) ?? ''));
                    $zonaHoja = $chunk['sheet_name'];
                    $paradas = $this->parseDecimal($this->valueByAliases($rowAssoc, ['paradas']));
                    $paquetes = $this->parseDecimal($this->valueByAliases($rowAssoc, ['paquetes']));
                    $entregados = $this->parseDecimal($this->valueByAliases($rowAssoc, ['entregados']));
                    if ($entregados === null) {
                        $entregados = $paquetes;
                    }
                    $paquetesAusentes = $this->parseDecimal($this->valueByAliases($rowAssoc, ['paquete ausente', 'paquetes ausentes', 'ausentes']));
                    $kilometros = $this->resolveKilometersValue($rowAssoc);
                    $zonaLejana = $this->parseBooleanish($this->valueByAliases($rowAssoc, ['zona lejana']));
                    $modelYear = $this->parseModelYear($this->valueByAliases($rowAssoc, ['modelo']));
                    $cantidad = $this->parseDecimal($this->valueByAliases($rowAssoc, ['entregados', 'paradas'])) ?? 1.0;
                    $zoneCalc = $this->resolveZoneCalculationConfig([$excelConcepto, $zonaHoja]);
                    $zonaId = $zoneCalc['zone_id'];
                    $calcType = $zoneCalc['calc_type'];

                    $sourceKey = $this->buildItemSourceKey(
                        $fecha,
                        $calcType,
                        $chunk['sheet_name'],
                        $rowAssoc,
                        [
                            'ruta' => $ruta,
                            'numero' => $numero,
                            'zona' => $excelConcepto,
                            'paradas' => $paradas,
                            'paquetes' => $paquetes,
                            'entregados' => $entregados,
                            'paquetes_ausentes' => $paquetesAusentes,
                            'kilometros' => $kilometros,
                            'vehicle_type' => $vehicleType,
                            'zona_lejana' => $zonaLejana ? 1 : 0,
                        ]
                    );

                    $concepto = $excelConcepto !== '' ? $excelConcepto : 'Viaje';
                    if ($excelConcepto === '' && ($ruta !== '' || $numero !== '')) {
                        $concepto = trim('Ruta ' . $ruta . ' #' . $numero);
                    }

                    $context = new SettlementContext(
                        $fecha,
                        (int) $transportista->id,
                        trim((string) $excelConcepto) !== '' ? trim((string) $excelConcepto) : null,
                        $zonaId,
                        $vehicleType,
                        $modelYear,
                        (float) $kilometros,
                        (float) ($entregados ?? $paquetes ?? 0),
                        (float) $paquetesAusentes,
                        (float) $paradas,
                        $zonaLejana
                    );

                    $breakdown = $this->ruleEngine->calculate($context);

                    if (!isset($this->itemsCache[$recibo->id])) {
                        $this->itemsCache[$recibo->id] = ReciboChoferItem::where('recibo_chofer_id', $recibo->id)
                            ->get()
                            ->keyBy('source_key')
                            ->all();
                    }

                    $existingItem = $this->itemsCache[$recibo->id][$sourceKey] ?? null;
                    
                    // Priority is the calculated Total Amount
                    [$importeUnitario, $importe] = $this->applyConceptSign($concepto, $breakdown->baseAmount, $breakdown->totalAmount);

                    $itemPayload = [
                        'recibo_chofer_id' => $recibo->id,
                        'concepto' => $concepto,
                        'cantidad' => $cantidad,
                        'zona' => $zoneCalc['zone_name'] ?? trim((string) $zonaHoja),
                        'paradas' => $paradas,
                        'paquetes' => $paquetes,
                        'entregados' => $entregados,
                        'importe_unitario' => $importeUnitario,
                        'importe' => $importe,
                        'meta' => array_merge(
                            $this->buildItemMeta($rowAssoc, $fecha, $chunk['sheet_name'], $rowNumber),
                            [
                                'calc_type' => $calcType,
                                'traffic_zone_id' => $zonaId,
                                'vehicle_type' => $vehicleType,
                                'transporte_id' => $vehicleContext['transporte_id'],
                                'model_year' => $modelYear,
                                'source_transportista_id' => $transportista->id,
                                'source_transportista_name' => $transportista->name,
                                'zona_lejana' => $zonaLejana,
                                'paquetes_ausentes' => $paquetesAusentes,
                                'kilometros_source' => $kilometros,
                                'receipt_owner_transportista_id' => $receiptOwnerId,
                                'driver_payment_fleet_id' => $receiptFleetId,
                                'pricing_breakdown' => $breakdown->toArray(),
                            ]
                        ),
                    ];

                    if ($existingItem) {
                        $existingItem->fill($itemPayload);
                        $existingItem->save();
                        $updated++;
                    } else {
                        try {
                            $newItem = ReciboChoferItem::create(array_merge($itemPayload, [
                                'source_key' => $sourceKey,
                            ]));
                            $this->itemsCache[$recibo->id][$sourceKey] = $newItem;
                            $created++;
                        } catch (\Illuminate\Database\QueryException $e) {
                            if ($e->errorInfo[1] == 1062 || $e->getCode() == 23000) {
                                $orphanedItem = ReciboChoferItem::where('source_key', $sourceKey)->first();
                                if ($orphanedItem) {
                                    $orphanedItem->fill($itemPayload);
                                    $orphanedItem->save();
                                    $this->itemsCache[$recibo->id][$sourceKey] = $orphanedItem;
                                    $updated++;
                                } else {
                                    throw $e;
                                }
                            } else {
                                throw $e;
                            }
                        }
                    }

                    $touchedReceipts[$recibo->id] = $recibo->id;
                } catch (\Throwable $e) {
                    $errors++;
                    $this->logRow($run, $chunk['sheet_name'], $rowNumber, 'error', $this->shortErrorMessage($e), $rowAssoc);
                }
            }
        });

        // Safely increment counts on the run
        $run->increment('rows_processed', $processed);
        $run->increment('rows_created', $created);
        $run->increment('rows_updated', $updated);
        $run->increment('rows_with_errors', $errors);

        $dbSummary = $run->summary;
        $dbTouched = $dbSummary['touched_receipts'] ?? [];
        foreach ($touchedReceipts as $rId) {
            if (!in_array($rId, $dbTouched)) {
                $dbTouched[] = $rId;
            }
        }
        $dbSummary['touched_receipts'] = $dbTouched;
        $run->summary = $dbSummary;
        $run->save();

        return [
            'processed' => $processed,
            'created' => $created,
            'updated' => $updated,
            'errors' => $errors,
        ];
    }

    public function importFromLogisticsRecords(DriverImportRun $run, \Illuminate\Support\Collection $records, string $quincenaOption, ?string $importMode = null): array
    {
        $this->assertRequiredTables();
        
        if ($importMode === null || $importMode === '') {
            $logisticsModeConfig = DriverImportSetting::where('setting_key', 'driver_payment_logistics_import_mode')->first();
            $importMode = is_string(optional($logisticsModeConfig)->setting_value) ? $logisticsModeConfig->setting_value : 'replace';
        }
        
        $defaultConfig = DriverImportSetting::where('setting_key', 'driver_payment_default_period_type')->first();
        $this->defaultPeriodType = is_string(optional($defaultConfig)->setting_value) ? $defaultConfig->setting_value : 'mensual';
        $this->receiptsCache = [];
        $this->itemsCache = [];
        $this->vehicleContextCache = [];
        $this->metaSynced = [];
        $this->fleetContextCache = [];
        $this->vehicleMapsCache = DriverImportVehicleMap::all()
            ->mapWithKeys(function (DriverImportVehicleMap $map) {
                return [Str::lower(Str::ascii(trim((string) $map->excel_value))) => $map->vehicle_type];
            })
            ->all();
        $this->aliasesCache = null;
        $this->zoneCalculationConfigIndex = null;
        $this->packageRateDefaultResolved = false;
        $this->packageRateDefaultCache = null;
        $this->kmRangesByZone = null;
        $this->configuredConceptsIndex = null;
        $this->zoneConceptYearValuesIndex = null;

        $created = 0;
        $updated = 0;
        $processed = 0;
        $errors = 0;
        $touchedReceipts = [];
        $transportistasIndex = [];

        if ($importMode === 'replace') {
            $logisticsRecordIds = $records->pluck('id')->filter()->all();
            if (! empty($logisticsRecordIds)) {
                $logisticsSet = array_flip($logisticsRecordIds);
                ReciboChoferItem::whereNotNull('meta')
                    ->chunkById(200, function ($items) use ($logisticsSet, &$touchedReceipts) {
                        foreach ($items as $item) {
                            $meta = is_array($item->meta) ? $item->meta : [];
                            $recordId = $meta['logistics_record_id'] ?? null;
                            if ($recordId && isset($logisticsSet[$recordId])) {
                                if ($item->recibo_chofer_id) {
                                    $touchedReceipts[$item->recibo_chofer_id] = $item->recibo_chofer_id;
                                }
                                $item->delete();
                            }
                        }
                    });
            }
        }

        DB::transaction(function () use (
            $records, $quincenaOption, $run,
            &$processed, &$created, &$updated, &$errors, &$touchedReceipts, &$transportistasIndex
        ) {
            foreach ($records as $index => $record) {
                $rowNumber = $index + 1;
                $processed++;

                // Map model columns to row assoc array expected by valueByAliases or internal methods
                $rowAssoc = [
                    'fecha' => $record->fecha ? $record->fecha->toDateString() : null,
                    'chofer' => $record->transportista ? $record->transportista->name : '',
                    'patente' => $record->transporte ? $record->transporte->license_plate : '',
                    'titular' => $record->transporte ? $record->transporte->owner_name : '',
                    'modelo' => $record->transporte ? $record->transporte->year : '',
                    'unidad' => $record->transporte ? $record->transporte->type : '',
                    'svc' => $record->svc,
                    'ruta' => $record->ruta,
                    'numero' => $record->numero,
                    'zona' => $record->zona,
                    'paradas' => $record->paradas,
                    'paquetes' => $record->paquetes,
                    'entregados' => $record->entregados,
                    'deja en el svc' => $record->deja_en_svc,
                    'paq no colectado' => $record->paq_no_colectado,
                    'nadie en domicilio' => $record->nadie_en_domicilio,
                    'negocio cerrado' => $record->negocio_cerrado,
                    'qr' => $record->qr,
                    'fuera de zona' => $record->fuera_de_zona,
                    'zona inaccesible' => $record->zona_inaccesible,
                    'rechazado' => $record->rechazado,
                    'sin visitar' => $record->sin_visitar,
                    'fraude' => $record->fraude,
                    'paquete perdido' => $record->paquete_perdido,
                    'paquete dañado' => $record->paquete_danado,
                    'paquete robado' => $record->paquete_robado,
                    'kilometros' => $record->kilometros,
                    'kilometros estimados' => $record->kilometros_estimados,
                    'observacion' => $record->observacion,
                ];

                try {
                    $fecha = Carbon::parse($record->fecha);
                    $chofer = $rowAssoc['chofer'];
                    if (trim((string) $chofer) === '') {
                        throw new \RuntimeException('Chofer vacío.');
                    }

                    $patente = trim((string) $rowAssoc['patente']);
                    $transportista = $record->transportista ?: $this->resolveTransportista($chofer, $patente, $rowAssoc, $transportistasIndex);
                    if (! $transportista) {
                        throw new \RuntimeException('No se pudo resolver transportista.');
                    }
                    $transportista->loadMissing(['transportes', 'liquidationMeta']);

                    if ($record->transporte && ! empty($record->transporte->type)) {
                        $vehicleContext = [
                            'transporte_id' => $record->transporte->id,
                            'vehicle_type' => $this->normalizeVehicleType($record->transporte->type),
                        ];
                        $vehicleType = $vehicleContext['vehicle_type'];
                    } else {
                        $vehicleContext = $this->resolveVehicleContext($transportista, $patente);
                        $vehicleType = $this->resolveVehicleTypeFromRow($rowAssoc, $vehicleContext['vehicle_type']);
                    }

                    $tipoPeriodo = $this->resolveTransportistaPeriodType($transportista, $rowAssoc);
                    if ($tipoPeriodo === null) {
                        throw new \RuntimeException('El transportista no tiene tipo de liquidación configurado.');
                    }

                    $periodo = $this->periodResolver->resolveForDate($fecha, $tipoPeriodo);
                    
                    if ($quincenaOption !== 'ambas' && isset($periodo['quincena'])) {
                        if ((int) $periodo['quincena'] !== (int) $quincenaOption) {
                            $processed--; // Don't count skipped rows as processed
                            continue;
                        }
                    }

                    $fleetContext = $this->resolveFleetContext($transportista);
                    $receiptOwnerId = (int) ($fleetContext['billing_transportista_id'] ?? $transportista->id);
                    $receiptFleetId = $fleetContext['fleet_id'] ?? null;
                    $receiptCacheKey = sha1(($receiptFleetId ?? 'driver:' . $receiptOwnerId) . '|' . $tipoPeriodo . '|' . $periodo['desde']->toDateString() . '|' . $periodo['hasta']->toDateString());

                    $svcPlaza = trim((string) $record->svc);
                    $trafficZonePlaza = optional($record->trafficZone)->name ? trim((string) $record->trafficZone->name) : '';
                    $driverPlaza = optional($transportista->liquidationMeta)->plaza ? trim((string) $transportista->liquidationMeta->plaza) : '';
                    $baseLocationPlaza = trim((string) $transportista->base_location);

                    $resolvedPlaza = $trafficZonePlaza !== ''
                        ? $trafficZonePlaza
                        : ($driverPlaza !== ''
                            ? $driverPlaza
                            : ($svcPlaza !== ''
                                ? $svcPlaza
                                : $baseLocationPlaza));

                    if (isset($this->receiptsCache[$receiptCacheKey])) {
                        $recibo = $this->receiptsCache[$receiptCacheKey];
                        if ((empty($recibo->plaza) || $recibo->plaza === 'Logística' || Str::startsWith($recibo->plaza, 'ZONA ')) && $resolvedPlaza !== '') {
                            $recibo->plaza = $resolvedPlaza;
                            $recibo->save();
                        }
                    } else {
                        $reciboQuery = ReciboChofer::query()
                            ->where('transportista_id', $receiptOwnerId)
                            ->where('tipo_periodo', $tipoPeriodo)
                            ->whereDate('periodo_desde', $periodo['desde']->toDateString())
                            ->whereDate('periodo_hasta', $periodo['hasta']->toDateString());

                        if ($receiptFleetId === null) {
                            $reciboQuery->whereNull('driver_payment_fleet_id');
                        } else {
                            $reciboQuery->where('driver_payment_fleet_id', $receiptFleetId);
                        }

                        $recibo = $reciboQuery->first();

                        if (! $recibo) {
                            $recibo = ReciboChofer::create([
                                'transportista_id' => $receiptOwnerId,
                                'driver_payment_fleet_id' => $receiptFleetId,
                                'tipo_periodo' => $tipoPeriodo,
                                'periodo_desde' => $periodo['desde']->toDateString(),
                                'periodo_hasta' => $periodo['hasta']->toDateString(),
                                'fecha_emision' => $fecha->toDateString(),
                                'plaza' => $resolvedPlaza,
                                'estado' => ReciboChofer::ESTADO_CARGADO,
                                'origen' => 'logistica',
                                'source_file' => $run->source_file,
                                'source_sheet' => 'Logística',
                                'source_key' => $receiptCacheKey,
                                'created_by' => $run->created_by,
                                'updated_by' => $run->created_by,
                            ]);
                        }
                        if ((empty($recibo->plaza) || $recibo->plaza === 'Logística' || Str::startsWith($recibo->plaza, 'ZONA ')) && $resolvedPlaza !== '') {
                            $recibo->plaza = $resolvedPlaza;
                            $recibo->save();
                        }
                        $this->receiptsCache[$receiptCacheKey] = $recibo;
                    }

                    if ($recibo->fecha_emision && $recibo->fecha_emision->isToday() && ! $fecha->isToday()) {
                        $recibo->fecha_emision = $fecha->toDateString();
                        $recibo->save();
                    }

                    $ruta = trim((string) $record->ruta);
                    $numero = trim((string) $record->numero);
                    $excelConcepto = trim((string) $record->zona);
                    $zonaHoja = 'Logística';
                    $paradas = (float) $record->paradas;
                    $paquetes = (float) $record->paquetes;
                    $entregados = (float) $record->entregados;
                    $paquetesAusentes = (float) ($record->paquetes - $record->entregados);
                    $kilometros = (float) $record->kilometros;
                    $zonaLejana = (bool) $record->zona_lejana;
                    $modelYear = $record->transporte ? $record->transporte->year : null;
                    $cantidad = (float) ($record->entregados ?? $record->paradas ?? 1.0);

                    $zoneCandidates = [
                        $trafficZonePlaza,
                        $driverPlaza,
                        $svcPlaza,
                        $excelConcepto,
                        $baseLocationPlaza,
                        $zonaHoja,
                    ];

                    $zoneCalc = $this->resolveZoneCalculationConfig($zoneCandidates);
                    $zonaId = $zoneCalc['zone_id'];
                    $calcType = $zoneCalc['calc_type'];

                    $sourceKey = $this->buildItemSourceKey(
                        $fecha,
                        $calcType,
                        $zonaHoja,
                        $rowAssoc,
                        [
                            'ruta' => $ruta,
                            'numero' => $numero,
                            'zona' => $excelConcepto,
                            'paradas' => $paradas,
                            'paquetes' => $paquetes,
                            'entregados' => $entregados,
                            'paquetes_ausentes' => $paquetesAusentes,
                            'kilometros' => $kilometros,
                            'vehicle_type' => $vehicleType,
                            'zona_lejana' => $zonaLejana ? 1 : 0,
                        ]
                    );

                    $concepto = $excelConcepto !== '' ? $excelConcepto : ($zoneCalc['zone_name'] ?? 'Viaje');
                    if ($excelConcepto === '' && ($ruta !== '' || $numero !== '')) {
                        $concepto = trim('Ruta ' . $ruta . ' #' . $numero);
                    }

                    $context = new SettlementContext(
                        $fecha,
                        (int) $transportista->id,
                        trim((string) $excelConcepto) !== '' ? trim((string) $excelConcepto) : ($zoneCalc['zone_name'] ?? null),
                        $zonaId,
                        $vehicleType,
                        $modelYear,
                        (float) $kilometros,
                        (float) ($entregados ?? $paquetes ?? 0),
                        (float) $paquetesAusentes,
                        (float) $paradas,
                        $zonaLejana
                    );

                    $breakdown = $this->ruleEngine->calculate($context);

                    if (!isset($this->itemsCache[$recibo->id])) {
                        $this->itemsCache[$recibo->id] = ReciboChoferItem::where('recibo_chofer_id', $recibo->id)
                            ->get()
                            ->keyBy('source_key')
                            ->all();
                    }

                    $existingItem = $this->itemsCache[$recibo->id][$sourceKey] ?? null;
                    
                    [$importeUnitario, $importe] = $this->applyConceptSign($concepto, $breakdown->baseAmount, $breakdown->totalAmount);

                    $itemPayload = [
                        'recibo_chofer_id' => $recibo->id,
                        'concepto' => $concepto,
                        'cantidad' => $cantidad,
                        'zona' => $zoneCalc['zone_name'] ?? trim((string) ($resolvedPlaza !== '' ? $resolvedPlaza : $zonaHoja)),
                        'paradas' => $paradas,
                        'paquetes' => $paquetes,
                        'entregados' => $entregados,
                        'importe_unitario' => $importeUnitario,
                        'importe' => $importe,
                        'meta' => array_merge(
                            $this->buildItemMeta($rowAssoc, $fecha, $zonaHoja, $rowNumber),
                            [
                                'calc_type' => $calcType,
                                'traffic_zone_id' => $zonaId,
                                'vehicle_type' => $vehicleType,
                                'transporte_id' => $record->transporte_id,
                                'model_year' => $modelYear,
                                'source_transportista_id' => $transportista->id,
                                'source_transportista_name' => $transportista->name,
                                'zona_lejana' => $zonaLejana,
                                'paquetes_ausentes' => $paquetesAusentes,
                                'kilometros_source' => $kilometros,
                                'receipt_owner_transportista_id' => $receiptOwnerId,
                                'driver_payment_fleet_id' => $receiptFleetId,
                                'pricing_breakdown' => $breakdown->toArray(),
                                'logistics_record_id' => $record->id,
                            ]
                        ),
                    ];

                    if ($existingItem) {
                        $existingItem->fill($itemPayload);
                        $existingItem->save();
                        $updated++;
                    } else {
                        try {
                            $newItem = ReciboChoferItem::create(array_merge($itemPayload, [
                                'source_key' => $sourceKey,
                            ]));
                            $this->itemsCache[$recibo->id][$sourceKey] = $newItem;
                            $created++;
                        } catch (\Illuminate\Database\QueryException $e) {
                            if ($e->errorInfo[1] == 1062 || $e->getCode() == 23000) {
                                $orphanedItem = ReciboChoferItem::where('source_key', $sourceKey)->first();
                                if ($orphanedItem) {
                                    $orphanedItem->fill($itemPayload);
                                    $orphanedItem->save();
                                    $this->itemsCache[$recibo->id][$sourceKey] = $orphanedItem;
                                    $updated++;
                                } else {
                                    throw $e;
                                }
                            } else {
                                throw $e;
                            }
                        }
                    }

                    $touchedReceipts[$recibo->id] = $recibo->id;
                } catch (\Throwable $e) {
                    $errors++;
                    $this->logRow($run, $zonaHoja, $rowNumber, 'error', $this->shortErrorMessage($e), $rowAssoc);
                }
            }
        });

        // Update counts on run
        $run->update([
            'rows_processed' => $processed,
            'rows_created' => $created,
            'rows_updated' => $updated,
            'rows_with_errors' => $errors,
        ]);

        // Sync rules and adjustments for touched receipts
        foreach (array_values($touchedReceipts) as $reciboId) {
            $recibo = ReciboChofer::find($reciboId);
            if ($recibo) {
                $this->receiptScopedRuleSynchronizer->sync($recibo);
                $this->syncAutomaticAdjustmentsForReceipt($recibo);
                $recibo->recalculateTotal();
            }
        }

        return [
            'processed' => $processed,
            'created' => $created,
            'updated' => $updated,
            'errors' => $errors,
        ];
    }

    public function finishImport(DriverImportRun $run): void
    {
        $summary = $run->summary;
        $touchedReceipts = $summary['touched_receipts'] ?? [];

        foreach ($touchedReceipts as $reciboId) {
            $recibo = ReciboChofer::find($reciboId);
            if ($recibo) {
                $this->receiptScopedRuleSynchronizer->sync($recibo);
                $this->syncAutomaticAdjustmentsForReceipt($recibo);
                $recibo->recalculateTotal();
            }
        }

        // Clean up temporary file
        $tempFile = $summary['temp_filepath'] ?? null;
        if ($tempFile && \Illuminate\Support\Facades\Storage::disk('local')->exists($tempFile)) {
            \Illuminate\Support\Facades\Storage::disk('local')->delete($tempFile);
        }
    }

    private function importBaseChoferes(Worksheet $sheet, DriverImportRun $run): array
    {
        $headerData = $this->resolveHeaderMap($sheet);
        $headerRow = $headerData['header_row'];
        $headerMap = $headerData['map'];
        $index = [];

        if (empty($headerMap)) {
            return $index;
        }

        $highestRow = $sheet->getHighestDataRow();
        for ($rowNumber = $headerRow + 1; $rowNumber <= $highestRow; $rowNumber++) {
            $rowAssoc = $this->extractRowAssoc($sheet, $rowNumber, $headerMap);
            if ($this->isOperationalRowEmpty($rowAssoc)) {
                continue;
            }

            $excelConceptoCheck = mb_strtolower(trim((string) ($this->valueByAliases($rowAssoc, ['zona']) ?? '')));
            if ($excelConceptoCheck === 'no se paga') {
                continue;
            }

            $chofer = trim((string) $this->valueByAliases($rowAssoc, ['chofer']));
            if ($chofer === '') {
                continue;
            }

            $patente = trim((string) $this->valueByAliases($rowAssoc, ['patente']));
            $transportista = Transportista::whereRaw('LOWER(name) = ?', [Str::lower(Str::ascii($chofer))])->first();
            if (! $transportista) {
                $transportista = Transportista::create([
                    'name' => $chofer,
                    'is_active' => true,
                ]);
            }

            TransportistaLiquidationMeta::updateOrCreate(
                ['transportista_id' => $transportista->id],
                [
                    'titular' => $this->valueByAliases($rowAssoc, ['titular']),
                    'patente' => $patente ?: null,
                    'modelo' => $this->valueByAliases($rowAssoc, ['modelo']),
                    'unidad' => $this->valueByAliases($rowAssoc, ['unidad']),
                    'plaza' => $this->valueByAliases($rowAssoc, ['plaza']),
                    'extra' => $rowAssoc,
                ]
            );

            $index[$this->carrierKey($chofer, $patente)] = $transportista->id;
        }

        return $index;
    }

    private function findSheetByName($workbook, string $name): ?Worksheet
    {
        foreach ($workbook->getSheetNames() as $sheetName) {
            if (Str::lower(trim($sheetName)) === Str::lower(trim($name))) {
                return $workbook->getSheetByName($sheetName);
            }
        }

        return null;
    }

    private function isIgnoredSheet(string $sheetName): bool
    {
        $normalized = Str::of($sheetName)->ascii()->lower()->trim();
        $normalized = preg_replace('/\s+/', ' ', (string) $normalized);

        return in_array($normalized, ['base choferes', 'flota madre', 'coordinadores', 'choferes'], true);
    }

    private function resolveHeaderMap(Worksheet $sheet): array
    {
        $highestRow = min($sheet->getHighestDataRow(), 30);
        $highestColumn = $sheet->getHighestDataColumn();
        $best = ['header_row' => 1, 'map' => []];

        for ($row = 1; $row <= $highestRow; $row++) {
            $range = 'A' . $row . ':' . $highestColumn . $row;
            $cells = $sheet->rangeToArray($range, null, true, true, true);
            $current = $cells[$row] ?? [];
            $map = [];

            foreach ($current as $column => $value) {
                $label = $this->normalizeHeader((string) $value);
                if ($label === '') {
                    continue;
                }
                $map[$label] = $column;
            }

            if (isset($map['chofer']) && (isset($map['fecha']) || isset($map['titular']) || isset($map['patente']))) {
                return [
                    'header_row' => $row,
                    'map' => $map,
                ];
            }

            if (count($map) > count($best['map'])) {
                $best = ['header_row' => $row, 'map' => $map];
            }
        }

        return $best;
    }

    private function extractRowAssoc(Worksheet $sheet, int $rowNumber, array $headerMap): array
    {
        $assoc = [];
        foreach ($headerMap as $header => $column) {
            $assoc[$header] = $this->readCellValue($sheet, $column, $rowNumber);
        }

        return $assoc;
    }

    private function readCellValue(Worksheet $sheet, string $column, int $rowNumber)
    {
        /** @var Cell $cell */
        $cell = $sheet->getCell($column . $rowNumber);

        try {
            if ($cell->isFormula()) {
                return $cell->getCalculatedValue();
            }
        } catch (\Throwable $e) {
            // Keep original content if formula calculation fails.
        }

        return $cell->getValue();
    }

    private function resolveTransportista(string $chofer, string $patente, array $rowAssoc, array &$transportistasIndex): ?Transportista
    {
        $key = $this->carrierKey($chofer, $patente);
        if (isset($transportistasIndex[$key])) {
            return $transportistasIndex[$key];
        }

        $transportista = Transportista::with(['transportes', 'liquidationMeta'])->whereRaw('LOWER(name) = ?', [Str::lower(Str::ascii($chofer))])->first();
        if (! $transportista) {
            $transportista = Transportista::create([
                'name' => $chofer,
                'is_active' => true,
                'base_location' => $this->valueByAliases($rowAssoc, ['plaza']),
            ]);
            $transportista->load(['transportes', 'liquidationMeta']);
        }

        if ($patente !== '' && !isset($this->metaSynced[$transportista->id])) {
            TransportistaLiquidationMeta::updateOrCreate(
                ['transportista_id' => $transportista->id],
                ['patente' => $patente, 'extra' => $rowAssoc]
            );
            $this->metaSynced[$transportista->id] = true;
        }

        $transportistasIndex[$key] = $transportista;

        return $transportista;
    }

    private function carrierKey(string $chofer, string $patente): string
    {
        return Str::lower(Str::ascii(trim($chofer))) . '|' . Str::lower(Str::ascii(trim($patente)));
    }

    private function resolveVehicleContext(Transportista $transportista, string $patente): array
    {
        $cacheKey = $transportista->id . '|' . $this->normalizePlate($patente);
        if (isset($this->vehicleContextCache[$cacheKey])) {
            return $this->vehicleContextCache[$cacheKey];
        }

        $transportes = $transportista->transportes->sortByDesc('is_default')->sortBy('id');
        $normalizedPlate = $this->normalizePlate($patente);
        $transporte = null;

        if ($normalizedPlate !== '') {
            $transporte = $transportes->first(function ($item) use ($normalizedPlate) {
                return $this->normalizePlate((string) $item->license_plate) === $normalizedPlate;
            });
        }

        if (! $transporte) {
            $transporte = $transportes->firstWhere('is_default', true) ?: $transportes->first();
        }

        $result = [
            'transporte_id' => $transporte ? $transporte->id : null,
            'vehicle_type' => $this->normalizeVehicleType($transporte ? $transporte->type : null),
        ];
        
        $this->vehicleContextCache[$cacheKey] = $result;
        return $result;
    }

    private function resolveVehicleTypeFromRow(array $rowAssoc, string $fallbackVehicleType = 'general'): string
    {
        $candidates = [
            $this->valueByAliases($rowAssoc, ['unidad']),
            $this->valueByAliases($rowAssoc, ['modelo', 'modelo vehiculo', 'modelo vehículo']),
            $this->valueByAliases($rowAssoc, ['comentarios', 'comentario', 'observacion', 'observación']),
        ];

        foreach ($candidates as $candidate) {
            $normalizedCandidate = Str::lower(Str::ascii(trim((string) $candidate)));
            if ($normalizedCandidate === '') {
                continue;
            }

            if (isset($this->vehicleMapsCache[$normalizedCandidate])) {
                return $this->vehicleMapsCache[$normalizedCandidate];
            }

            $normalizedType = $this->normalizeVehicleType((string) $candidate);
            if ($normalizedType !== 'general') {
                return $normalizedType;
            }
        }

        return $fallbackVehicleType;
    }

    private function normalizePlate(string $value): string
    {
        return strtoupper((string) preg_replace('/[^A-Z0-9]+/', '', Str::ascii($value)));
    }

    private function normalizeVehicleType(?string $value, bool $allowGeneral = true): string
    {
        $normalized = $this->normalizeHeader((string) $value);
        $map = [
            'moto' => Transporte::TYPE_MOTO,
            'motocicleta' => Transporte::TYPE_MOTO,
            'camioneta' => Transporte::TYPE_CAMIONETA,
            'utilitario' => Transporte::TYPE_CAMIONETA,
            'camioneta mediana' => Transporte::TYPE_CAMIONETA_MEDIANA,
            'camioneta mediano' => Transporte::TYPE_CAMIONETA_MEDIANA,
            'camioneta grande' => Transporte::TYPE_CAMIONETA_GRANDE,
            'camion grande' => Transporte::TYPE_CAMIONETA_GRANDE,
            'furgon grande' => Transporte::TYPE_CAMIONETA_GRANDE,
        ];

        if (isset($map[$normalized])) {
            return $map[$normalized];
        }

        if (array_key_exists($normalized, Transporte::paymentVehicleTypes())) {
            return $normalized;
        }

        return $allowGeneral ? 'general' : Transporte::TYPE_CAMIONETA;
    }

    private function valueByAliases(array $row, array $aliases)
    {
        $configured = $this->configuredAliases();
        $resolved = [];

        foreach ($aliases as $alias) {
            $key = $this->normalizeHeader($alias);
            if (isset($configured[$key]) && is_array($configured[$key])) {
                foreach ($configured[$key] as $candidate) {
                    $resolved[] = $this->normalizeHeader((string) $candidate);
                }
            }
            $resolved[] = $key;
        }

        foreach (array_values(array_unique($resolved)) as $alias) {
            if (array_key_exists($alias, $row) && $row[$alias] !== null && trim((string) $row[$alias]) !== '') {
                return $row[$alias];
            }
        }

        return null;
    }

    private function configuredAliases(): array
    {
        if ($this->aliasesCache !== null) {
            return $this->aliasesCache;
        }

        $default = [
            'numero' => ['numero', 'nro', 'nro ruta', 'num'],
            'paradas' => ['paradas', 'stops'],
            'entregados' => ['entregados', 'delivered', 'paquetes', 'paqs', 'bultos'],
            'paquetes' => ['paquetes', 'paqs', 'bultos'],
            'plaza' => ['plaza', 'sucursal'],
            'kilometros' => ['kilometraje', 'kilometros', 'kilometros estimados', 'km'],
            'paquete ausente' => ['paquete ausente', 'paquetes ausentes', 'ausente', 'ausentes'],
            'zona lejana' => ['zona lejana', 'lejana'],
            'tipo periodo' => ['tipo transporte', 'tipo_transporte', 'tipo liquidacion', 'tipo_liquidacion', 'tipo periodo', 'tipo_periodo'],
        ];

        $record = DriverImportSetting::where('setting_key', 'excel_column_map')->first();
        $payload = is_array(optional($record)->setting_value) ? $record->setting_value : [];
        $this->aliasesCache = array_merge($default, $payload);

        return $this->aliasesCache;
    }

    private function parseDecimal($value): ?float
    {
        if ($value === null) {
            return null;
        }

        $clean = preg_replace('/[^\d,\.\-]/', '', (string) $value);
        $clean = str_replace(',', '.', $clean);
        $clean = trim($clean);

        return ($clean !== '' && is_numeric($clean)) ? (float) $clean : null;
    }

    private function parseModelYear($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            $year = (int) $value;
            return ($year >= 1900 && $year <= 2100) ? $year : null;
        }

        if (preg_match('/\b(19\d{2}|20\d{2}|2100)\b/', (string) $value, $matches)) {
            return (int) $matches[1];
        }

        return null;
    }

    private function parseBooleanish($value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        $normalized = $this->normalizeHeader((string) $value);
        if ($normalized === '') {
            return false;
        }

        if (in_array($normalized, ['1', 'si', 's', 'yes', 'true', 'x'], true)) {
            return true;
        }

        return is_numeric($value) ? (float) $value > 0 : false;
    }

    private function parseDate($value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance(\DateTime::createFromInterface($value));
        }

        if (is_numeric($value)) {
            try {
                return Carbon::instance(ExcelDate::excelToDateTimeObject($value));
            } catch (\Throwable $e) {
                return null;
            }
        }

        $value = trim(str_replace("\xc2\xa0", ' ', (string) $value));

        // Normalize cases where SheetJS SSF date formatting bug drops the second slash:
        // e.g., '27/72026' -> '27/7/2026', '25/072026' -> '25/07/2026'
        if (preg_match('/^(\d{1,2})\/(\d{1,2})(\d{4})$/', $value, $matches)) {
            $value = $matches[1] . '/' . $matches[2] . '/' . $matches[3];
        }

        $formats = [
            'd/m/y',
            'd/m/Y',
            'd-m-y',
            'd-m-Y',
            'Y-m-d',
            'd/m/y H:i',
            'd/m/Y H:i',
            'd/m/y H:i:s',
            'd/m/Y H:i:s',
            'Y-m-d H:i:s',
        ];

        foreach ($formats as $format) {
            try {
                return Carbon::createFromFormat($format, $value);
            } catch (\Throwable $e) {
                continue;
            }
        }

        if (is_numeric($value)) {
            try {
                return Carbon::instance(ExcelDate::excelToDateTimeObject((float) $value));
            } catch (\Throwable $e) {
                // continue
            }
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function buildItemMeta(array $rowAssoc, Carbon $fecha, string $sheetName, int $rowNumber): array
    {
        return [
            'sheet' => $sheetName,
            'row' => $rowNumber,
            'fecha' => $fecha->toDateString(),
            'chofer' => $this->valueByAliases($rowAssoc, ['chofer']),
            'titular' => $this->valueByAliases($rowAssoc, ['titular']),
            'patente' => $this->valueByAliases($rowAssoc, ['patente']),
            'modelo' => $this->valueByAliases($rowAssoc, ['modelo', 'modelo vehiculo', 'modelo vehículo']),
            'unidad' => $this->valueByAliases($rowAssoc, ['unidad']),
            'ruta' => $this->valueByAliases($rowAssoc, ['ruta']),
            'numero' => $this->valueByAliases($rowAssoc, ['numero', 'nro']),
            'zona' => $this->valueByAliases($rowAssoc, ['zona']),
            'zona_hoja' => $sheetName,
            'paradas' => $this->parseDecimal($this->valueByAliases($rowAssoc, ['paradas'])),
            'paquetes' => $this->parseDecimal($this->valueByAliases($rowAssoc, ['paquetes'])),
            'entregados' => $this->parseDecimal($this->valueByAliases($rowAssoc, ['entregados'])) ?? $this->parseDecimal($this->valueByAliases($rowAssoc, ['paquetes'])),
            'paquetes_ausentes' => $this->parseDecimal($this->valueByAliases($rowAssoc, ['paquete ausente', 'paquetes ausentes', 'ausentes'])),
            'zona_lejana' => $this->parseBooleanish($this->valueByAliases($rowAssoc, ['zona lejana'])),
            'deja_en_svc' => $this->parseDecimal($this->valueByAliases($rowAssoc, ['deja en el svc'])),
            'paq_no_colectado' => $this->parseDecimal($this->valueByAliases($rowAssoc, ['paq no colectado', 'paq. no colectado'])),
            'nadie_en_domicilio' => $this->parseDecimal($this->valueByAliases($rowAssoc, ['nadie en domicilio'])),
            'negocio_cerrado' => $this->parseDecimal($this->valueByAliases($rowAssoc, ['negocio cerrado'])),
            'qr' => $this->parseDecimal($this->valueByAliases($rowAssoc, ['qr'])),
            'fuera_de_zona' => $this->parseDecimal($this->valueByAliases($rowAssoc, ['fuera de zona'])),
            'zona_inaccesible' => $this->parseDecimal($this->valueByAliases($rowAssoc, ['zona inaccesible'])),
            'rechazado' => $this->parseDecimal($this->valueByAliases($rowAssoc, ['rechazado'])),
            'sin_visitar' => $this->parseDecimal($this->valueByAliases($rowAssoc, ['sin visitar'])),
            'fraude' => $this->parseDecimal($this->valueByAliases($rowAssoc, ['fraude'])),
            'paquete_perdido' => $this->parseDecimal($this->valueByAliases($rowAssoc, ['paquete perdido'])),
            'paquete_danado' => $this->parseDecimal($this->valueByAliases($rowAssoc, ['paquete dañado', 'paquete danado'])),
            'paquete_robado' => $this->parseDecimal($this->valueByAliases($rowAssoc, ['paquete robado'])),
            'porcentaje' => $this->parseDecimal($this->valueByAliases($rowAssoc, ['porcentaje'])),
            'kilometros' => $this->resolveKilometersValue($rowAssoc),
            'kilometros_estimados' => $this->parseDecimal($this->valueByAliases($rowAssoc, ['kilometros estimados', 'kilometraje estimado'])),
            'observacion' => $this->valueByAliases($rowAssoc, ['observacion', 'observación']),
            'raw' => $rowAssoc,
        ];
    }

    private function resolveKilometersValue(array $rowAssoc): ?float
    {
        $direct = $this->parseDecimal($this->valueByAliases($rowAssoc, [
            'kilometraje',
            'kilometrajes',
            'kilometros',
            'kilometros estimados',
            'kilometraje estimado',
            'km',
        ]));

        if ($direct !== null) {
            return $direct;
        }

        foreach ($rowAssoc as $header => $value) {
            $normalizedHeader = $this->normalizeHeader((string) $header);

            if ($normalizedHeader === 'km' || str_contains($normalizedHeader, 'kilometra')) {
                $parsed = $this->parseDecimal($value);
                if ($parsed !== null) {
                    return $parsed;
                }
            }
        }

        return null;
    }

    private function resolveSheetPeriod(Worksheet $sheet, int $headerRow, array $headerMap, string $tipoPeriodo): array
    {
        $highestRow = $sheet->getHighestDataRow();
        $minDate = null;
        $maxDate = null;

        for ($rowNumber = $headerRow + 1; $rowNumber <= $highestRow; $rowNumber++) {
            $rowAssoc = $this->extractRowAssoc($sheet, $rowNumber, $headerMap);
            if ($this->isOperationalRowEmpty($rowAssoc)) {
                continue;
            }

            $excelConceptoCheck = mb_strtolower(trim((string) ($this->valueByAliases($rowAssoc, ['zona']) ?? '')));
            if ($excelConceptoCheck === 'no se paga') {
                continue;
            }

            $rawFecha = $this->valueByAliases($rowAssoc, ['fecha']);
            if ($rawFecha === null || trim((string) $rawFecha) === '') {
                $rawFecha = $this->readCellValue($sheet, 'A', $rowNumber);
            }

            $fecha = $this->parseDate($rawFecha);
            if (! $fecha) {
                continue;
            }

            if ($minDate === null || $fecha->lt($minDate)) {
                $minDate = $fecha->copy()->startOfDay();
            }
            if ($maxDate === null || $fecha->gt($maxDate)) {
                $maxDate = $fecha->copy()->startOfDay();
            }
        }

        if ($minDate && $maxDate) {
            return [
                'desde' => $minDate,
                'hasta' => $maxDate,
                'quincena' => null,
                'setting_id' => null,
            ];
        }

        // Fallback to existing resolver behavior if the sheet has no valid dates.
        return $this->periodResolver->resolveForDate(now(), $tipoPeriodo);
    }

    private function resolveTransportistaPeriodType(Transportista $transportista, array $rowAssoc = []): ?string
    {
        $excelType = trim(strtolower((string) $this->valueByAliases($rowAssoc, ['tipo_periodo'])));
        if (in_array($excelType, [ReciboChofer::TIPO_QUINCENAL, ReciboChofer::TIPO_MENSUAL], true)) {
            return $excelType;
        }

        $meta = $transportista->relationLoaded('liquidationMeta')
            ? $transportista->liquidationMeta
            : $transportista->liquidationMeta()->first();

        $tipoPeriodo = trim((string) optional($meta)->tipo_periodo);

        if (in_array($tipoPeriodo, [ReciboChofer::TIPO_QUINCENAL, ReciboChofer::TIPO_MENSUAL], true)) {
            return $tipoPeriodo;
        }
        
        return $this->defaultPeriodType;
    }

    private function normalizeHeader(string $value): string
    {
        static $cache = [];
        if (isset($cache[$value])) {
            return $cache[$value];
        }

        $normalized = Str::of($value)->ascii()->lower()->trim();
        $normalized = preg_replace('/[^a-z0-9]+/', ' ', (string) $normalized);

        $result = trim((string) $normalized);
        $cache[$value] = $result;
        return $result;
    }



    private function resolveZoneCalculationConfig($zoneCandidates): array
    {
        $zoneCandidates = is_array($zoneCandidates) ? $zoneCandidates : [$zoneCandidates];
        $zoneCandidates = array_values(array_filter(array_map(static fn ($value) => trim((string) $value), $zoneCandidates), static fn ($value) => $value !== ''));
        $fallbackName = $zoneCandidates[0] ?? 'Sin zona';

        $default = [
            'zone_id' => null,
            'zone_name' => $fallbackName,
            'calc_type' => DriverPaymentZoneSetting::TYPE_ZONA,
            'package_rate' => null,
            'km_package_threshold' => null,
            'km_excess_package_amount' => null,
            'km_remote_zone_plus_large' => null,
            'package_delivered_rate' => null,
            'package_absent_rate_multiplier' => null,
            'package_fixed_amount' => null,
            'package_excess_threshold' => null,
            'package_excess_amount' => null,
        ];

        if (! Schema::hasTable('traffic_zones') || ! Schema::hasTable('driver_payment_zone_settings')) {
            return $default;
        }

        if ($this->zoneCalculationConfigIndex === null) {
            $this->zoneCalculationConfigIndex = [];
            $zones = TrafficZone::query()->orderBy('id')->get(['id', 'name']);
            $zoneSettings = DriverPaymentZoneSetting::query()->get()->keyBy('traffic_zone_id');
            foreach ($zones as $zone) {
                $setting = $zoneSettings->get($zone->id);
                $this->zoneCalculationConfigIndex[$this->normalizeHeader($zone->name)] = [
                    'zone_id' => $zone->id,
                    'zone_name' => $zone->name,
                    'calc_type' => $setting ? $setting->calc_type : DriverPaymentZoneSetting::TYPE_ZONA,
                    'package_rate' => $setting ? (float) $setting->package_rate : null,
                    'km_package_threshold' => $setting ? $setting->km_package_threshold : null,
                    'km_excess_package_amount' => $setting ? (float) $setting->km_excess_package_amount : null,
                    'km_remote_zone_plus_large' => $setting ? (float) $setting->km_remote_zone_plus_large : null,
                    'package_delivered_rate' => $setting ? (float) $setting->package_delivered_rate : null,
                    'package_absent_rate_multiplier' => $setting ? (float) $setting->package_absent_rate_multiplier : null,
                    'package_fixed_amount' => $setting ? (float) $setting->package_fixed_amount : null,
                    'package_excess_threshold' => $setting ? $setting->package_excess_threshold : null,
                    'package_excess_amount' => $setting ? (float) $setting->package_excess_amount : null,
                ];
            }
        }


        foreach ($zoneCandidates as $candidate) {
            $key = $this->normalizeHeader($candidate);
            if (isset($this->zoneCalculationConfigIndex[$key])) {
                return $this->zoneCalculationConfigIndex[$key];
            }
        }

        return $default;
    }

    private function buildItemSourceKey(Carbon $fecha, string $calcType, string $sheetName, array $rowAssoc, array $resolvedValues): string
    {
        $parts = [
            $fecha->toDateString(),
            $this->normalizeHeader($calcType),
            $this->normalizeHeader($sheetName),
            $this->normalizeHeader((string) $this->valueByAliases($rowAssoc, ['chofer'])),
            $this->normalizeHeader((string) ($resolvedValues['vehicle_type'] ?? 'general')),
        ];

        if ($calcType === DriverPaymentZoneSetting::TYPE_KM) {
            $parts[] = $this->normalizeHeader((string) $this->valueByAliases($rowAssoc, ['turno']));
            $parts[] = (string) ($resolvedValues['kilometros'] ?? '');
            $parts[] = (string) ($resolvedValues['paquetes'] ?? '');
            $parts[] = (string) ($resolvedValues['entregados'] ?? '');
            $parts[] = (string) ($resolvedValues['zona_lejana'] ?? '');
            $parts[] = $this->normalizeHeader((string) $this->valueByAliases($rowAssoc, ['comentarios', 'comentario', 'observacion']));
        } elseif ($calcType === DriverPaymentZoneSetting::TYPE_PAQUETE) {
            $parts[] = $this->normalizeHeader((string) ($resolvedValues['zona'] ?? ''));
            $parts[] = $this->normalizeHeader((string) $this->valueByAliases($rowAssoc, ['sucursal']));
            $parts[] = $this->normalizeHeader((string) $this->valueByAliases($rowAssoc, ['planilla numero', 'planilla', 'numero planilla']));
            $parts[] = (string) ($resolvedValues['paquetes'] ?? '');
            $parts[] = (string) ($resolvedValues['entregados'] ?? '');
            $parts[] = (string) ($resolvedValues['paquetes_ausentes'] ?? '');
        } else {
            $parts[] = $this->normalizeHeader((string) ($resolvedValues['ruta'] ?? ''));
            $parts[] = $this->normalizeHeader((string) ($resolvedValues['numero'] ?? ''));
            $parts[] = $this->normalizeHeader((string) ($resolvedValues['zona'] ?? ''));
        }

        return sha1(implode('|', $parts));
    }

    private function resolveReceiptPlaza(array $rowAssoc, Transportista $transportista, string $sheetName): string
    {
        $plaza = trim((string) ($this->valueByAliases($rowAssoc, ['plaza']) ?: optional($transportista->liquidationMeta)->plaza));

        if ($plaza !== '') {
            return $plaza;
        }

        return trim($sheetName);
    }

    private function resolveFleetContext(Transportista $transportista): array
    {
        if (array_key_exists($transportista->id, $this->fleetContextCache)) {
            return $this->fleetContextCache[$transportista->id];
        }

        $fleet = $transportista->paymentFleet()
            ->where('driver_payment_fleets.active', true)
            ->with('billingTransportista:id,name')
            ->first();

        return $this->fleetContextCache[$transportista->id] = [
            'fleet_id' => $fleet ? $fleet->id : null,
            'fleet_name' => $fleet ? $fleet->name : null,
            'billing_transportista_id' => $fleet && $fleet->billing_transportista_id ? $fleet->billing_transportista_id : $transportista->id,
        ];
    }

    private function applyConceptSign(string $conceptName, float $unitAmount, float $totalAmount): array
    {
        $concept = $this->findConceptByName($conceptName);
        if (! $concept || (int) $concept->sign !== -1) {
            return [$unitAmount, $totalAmount];
        }

        return [abs($unitAmount) * -1, abs($totalAmount) * -1];
    }

    private function findConceptByName(?string $conceptName): ?DriverPaymentConcept
    {
        $normalized = Str::lower(Str::ascii(trim((string) $conceptName)));
        if ($normalized === '') {
            return null;
        }

        if ($this->configuredConceptsIndex === null) {
            $this->configuredConceptsIndex = DriverPaymentConcept::query()
                ->get()
                ->keyBy(fn (DriverPaymentConcept $concept) => Str::lower(Str::ascii(trim((string) $concept->name)))
                )
                ->all();
        }

        return $this->configuredConceptsIndex[$normalized] ?? null;
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

                if ($rule->traffic_zone_id !== null && (int) ($meta['traffic_zone_id'] ?? 0) !== (int) $rule->traffic_zone_id) {
                    return false;
                }

                if (($meta['receipt_rule'] ?? false) === true) {
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

    private function isOperationalRowEmpty(array $row): bool
    {
        foreach ($row as $value) {
            if ($value !== null && trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    private function logRow(DriverImportRun $run, ?string $sheet, ?int $rowNumber, string $status, ?string $message, ?array $payload = null): void
    {
        $safeMessage = $message !== null ? mb_substr($message, 0, 1000) : null;

        try {
            DriverImportRunRow::create([
                'driver_import_run_id' => $run->id,
                'sheet_name' => $sheet,
                'row_number' => $rowNumber,
                'status' => $status,
                'message' => $safeMessage,
                'payload' => $payload,
            ]);
        } catch (\Throwable $e) {
            Log::warning('No se pudo guardar log de fila de importacion.', [
                'run_id' => $run->id,
                'sheet' => $sheet,
                'row' => $rowNumber,
                'status' => $status,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function shortErrorMessage(\Throwable $e): string
    {
        $msg = trim((string) $e->getMessage());
        if ($msg === '') {
            $msg = 'Error no especificado.';
        }

        return mb_substr($msg, 0, 900);
    }

    private function assertRequiredTables(): void
    {
        $required = [
            'recibos_chofer',
            'recibo_chofer_items',
            'driver_import_runs',
            'driver_import_run_rows',
        ];

        foreach ($required as $table) {
            if (!Schema::hasTable($table)) {
                throw new \RuntimeException("Falta la tabla '{$table}'. Ejecuta php artisan migrate.");
            }
        }
    }
}
