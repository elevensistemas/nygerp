<?php

namespace App\Services\DriverPayments;

use App\Models\DriverImportRun;
use App\Models\DriverLogisticsRecord;
use App\Models\ReciboChofer;
use App\Models\ReciboChoferItem;
use Illuminate\Support\Facades\Log;

class DriverLogisticsAutoSyncService
{
    /**
     * Flag to prevent nested/duplicate sync executions during bulk operations.
     */
    public static bool $isSyncing = false;

    private DriverExcelImporter $importer;

    public function __construct(DriverExcelImporter $importer)
    {
        $this->importer = $importer;
    }

    /**
     * Synchronize modified and deleted logistics records into driver payment receipts.
     *
     * @param array<int> $savedRecordIds List of DriverLogisticsRecord IDs that were created or updated.
     * @param array<int> $deletedRecordIds List of DriverLogisticsRecord IDs that were deleted.
     */
    public function syncByRecordIds(array $savedRecordIds = [], array $deletedRecordIds = []): void
    {
        if (empty($savedRecordIds) && empty($deletedRecordIds)) {
            return;
        }

        self::$isSyncing = true;

        try {
            $touchedReceiptIds = [];

            // 1. Process deleted records first
            if (! empty($deletedRecordIds)) {
                $logisticsSet = array_flip(array_map('intval', $deletedRecordIds));
                ReciboChoferItem::whereNotNull('meta')
                    ->chunkById(200, function ($items) use ($logisticsSet, &$touchedReceiptIds) {
                        foreach ($items as $item) {
                            $meta = is_array($item->meta) ? $item->meta : [];
                            $recId = isset($meta['logistics_record_id']) ? (int) $meta['logistics_record_id'] : null;
                            if ($recId && isset($logisticsSet[$recId])) {
                                if ($item->recibo_chofer_id) {
                                    $touchedReceiptIds[$item->recibo_chofer_id] = $item->recibo_chofer_id;
                                }
                                $item->delete();
                            }
                        }
                    });
            }

            // 2. Process created/updated records
            if (! empty($savedRecordIds)) {
                $records = DriverLogisticsRecord::with([
                    'transportista.liquidationMeta',
                    'transporte',
                    'trafficZone',
                ])
                ->whereIn('id', $savedRecordIds)
                ->orderBy('fecha')
                ->get();

                if ($records->isNotEmpty()) {
                    $run = DriverImportRun::create([
                        'source_file' => 'Sincronización Automática Planilla',
                        'tipo_periodo' => 'mixto',
                        'created_by' => auth()->id(),
                        'summary' => [
                            'quincena_option' => 'ambas',
                            'import_mode' => 'replace',
                            'touched_receipts' => [],
                            'is_logistics' => true,
                            'is_auto_sync' => true,
                        ],
                        'rows_processed' => 0,
                        'rows_created' => 0,
                        'rows_updated' => 0,
                        'rows_with_errors' => 0,
                    ]);

                    $this->importer->importFromLogisticsRecords($run, $records, 'ambas', 'replace');

                    $summary = is_array($run->summary) ? $run->summary : [];
                    $importTouched = $summary['touched_receipts'] ?? [];
                    foreach ($importTouched as $rId) {
                        $touchedReceiptIds[$rId] = $rId;
                    }
                }
            }

            // 3. Recalculate/clean any touched receipts
            foreach ($touchedReceiptIds as $reciboId) {
                $recibo = ReciboChofer::find($reciboId);
                if ($recibo) {
                    $this->importer->receiptScopedRuleSynchronizer->sync($recibo);
                    $this->importer->syncAutomaticAdjustmentsForReceipt($recibo);
                    $recibo->recalculateTotal();

                    // If receipt is now completely empty (0 items) and is in draft/cargado state with no advances, clean it up
                    if ($recibo->items()->count() === 0 && $recibo->estado === ReciboChofer::ESTADO_CARGADO && $recibo->advanceRequests()->count() === 0) {
                        $recibo->delete();
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::error('Error en DriverLogisticsAutoSyncService: ' . $e->getMessage(), [
                'exception' => $e,
                'savedRecordIds' => $savedRecordIds,
                'deletedRecordIds' => $deletedRecordIds,
            ]);
        } finally {
            self::$isSyncing = false;
        }
    }
}
