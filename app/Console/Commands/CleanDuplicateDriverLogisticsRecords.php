<?php

namespace App\Console\Commands;

use App\Models\DriverLogisticsRecord;
use App\Models\DriverLogisticsRecordLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CleanDuplicateDriverLogisticsRecords extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'driver-logistics:clean-duplicates {--dry-run : Solamente identificar duplicados sin eliminar} {--fecha= : Filtrar por una fecha específica (YYYY-MM-DD)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Identifica y elimina registros duplicados en la planilla diaria de choferes';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $isDryRun = $this->option('dry-run');
        $specificDate = $this->option('fecha');

        $this->info($isDryRun ? '--- MODO SIMULACIÓN (DRY-RUN) ---' : '--- EJECUTANDO LIMPIEZA DE DUPLICADOS ---');

        $result = static::cleanDuplicates($specificDate, $isDryRun);

        $this->info("Grupos de duplicados encontrados: {$result['duplicate_groups_count']}");
        $this->info("Total de registros a eliminar: {$result['total_deleted_count']}");

        if ($isDryRun) {
            $this->warn('Modo simulación finalizado. No se realizaron cambios en la base de datos.');
        } else {
            $this->info('Limpieza completada exitosamente.');
        }

        return 0;
    }

    /**
     * Core logic to clean duplicate records.
     *
     * @param string|null $specificDate
     * @param bool $isDryRun
     * @param string|null $fechaDesde
     * @param string|null $fechaHasta
     * @return array
     */
    public static function cleanDuplicates(?string $specificDate = null, bool $isDryRun = false, ?string $fechaDesde = null, ?string $fechaHasta = null): array
    {
        $query = DriverLogisticsRecord::query();

        if ($specificDate) {
            $query->whereDate('fecha', $specificDate);
        } elseif ($fechaDesde && $fechaHasta) {
            $query->whereBetween('fecha', [$fechaDesde, $fechaHasta]);
        }

        $allRecords = $query->orderBy('fecha')->orderBy('id')->get();

        // Group records by unique combination of key fields
        $grouped = [];

        foreach ($allRecords as $record) {
            $fechaStr = $record->fecha instanceof \Carbon\Carbon ? $record->fecha->format('Y-m-d') : substr((string)$record->fecha, 0, 10);
            $keyParts = [
                $fechaStr,
                $record->transportista_id,
                $record->traffic_zone_id ?? 'null',
                $record->transporte_id ?? 'null',
                trim((string)($record->ruta ?? '')),
                trim((string)($record->numero ?? '')),
                trim((string)($record->zona ?? '')),
            ];

            $groupKey = implode('|', $keyParts);
            $grouped[$groupKey][] = $record;
        }

        $duplicateGroupsCount = 0;
        $totalDeletedCount = 0;
        $deletedRecordIds = [];

        DB::transaction(function () use ($grouped, $isDryRun, &$duplicateGroupsCount, &$totalDeletedCount, &$deletedRecordIds) {
            foreach ($grouped as $groupKey => $records) {
                if (count($records) <= 1) {
                    continue;
                }

                $duplicateGroupsCount++;

                // Score records to find the best candidate to keep
                usort($records, function ($a, $b) {
                    $scoreA = static::calculateDetailScore($a);
                    $scoreB = static::calculateDetailScore($b);

                    if ($scoreA === $scoreB) {
                        // If scores match, prioritize lowest ID (original record)
                        return $a->id <=> $b->id;
                    }

                    return $scoreB <=> $scoreA; // Highest score first
                });

                $keepRecord = $records[0];
                $duplicatesToDelete = array_slice($records, 1);

                // Merge non-zero metric values into the kept record if missing
                $mergedData = [];
                $numericFields = [
                    'paradas', 'paquetes', 'entregados', 'deja_en_svc', 'paq_no_colectado',
                    'nadie_en_domicilio', 'negocio_cerrado', 'qr', 'fuera_de_zona',
                    'zona_inaccesible', 'rechazado', 'sin_visitar', 'fraude',
                    'paquete_perdido', 'paquete_danado', 'paquete_robado', 'kilometros', 'kilometros_estimados'
                ];

                foreach ($duplicatesToDelete as $dup) {
                    foreach ($numericFields as $field) {
                        if (($keepRecord->{$field} ?? 0) == 0 && ($dup->{$field} ?? 0) > 0) {
                            $mergedData[$field] = $dup->{$field};
                            $keepRecord->{$field} = $dup->{$field};
                        }
                    }

                    $stringFields = ['comentario_perdido', 'comentario_danado', 'comentario_robado', 'observacion'];
                    foreach ($stringFields as $field) {
                        if (empty($keepRecord->{$field}) && !empty($dup->{$field})) {
                            $mergedData[$field] = $dup->{$field};
                            $keepRecord->{$field} = $dup->{$field};
                        }
                    }
                }

                if (!empty($mergedData) && !$isDryRun) {
                    $keepRecord->save();
                }

                foreach ($duplicatesToDelete as $dup) {
                    $totalDeletedCount++;
                    $deletedRecordIds[] = $dup->id;

                    if (!$isDryRun) {
                        $carrierName = $dup->transportista ? $dup->transportista->name : 'N/A';
                        $fechaStr = $dup->fecha instanceof \Carbon\Carbon ? $dup->fecha->format('Y-m-d') : substr((string)$dup->fecha, 0, 10);
                        
                        DriverLogisticsRecordLog::create([
                            'driver_logistics_record_id' => $dup->id,
                            'user_id' => auth()->id() ?? null,
                            'action' => 'eliminar',
                            'details' => "Eliminado automáticamente por limpieza de duplicados (conservado ID #{$keepRecord->id}) para el chofer {$carrierName} en la fecha {$fechaStr}.",
                        ]);

                        $dup->delete();
                    }
                }
            }
        });

        return [
            'duplicate_groups_count' => $duplicateGroupsCount,
            'total_deleted_count' => $totalDeletedCount,
            'deleted_ids' => $deletedRecordIds,
        ];
    }

    /**
     * Score a record based on how much detail/data it contains.
     *
     * @param DriverLogisticsRecord $record
     * @return int
     */
    protected static function calculateDetailScore(DriverLogisticsRecord $record): int
    {
        $score = 0;
        $fields = [
            'paradas', 'paquetes', 'entregados', 'deja_en_svc', 'paq_no_colectado',
            'nadie_en_domicilio', 'negocio_cerrado', 'qr', 'fuera_de_zona',
            'zona_inaccesible', 'rechazado', 'sin_visitar', 'fraude',
            'paquete_perdido', 'paquete_danado', 'paquete_robado', 'kilometros', 'kilometros_estimados'
        ];

        foreach ($fields as $f) {
            if (!empty($record->{$f}) && (float)$record->{$f} > 0) {
                $score += 2;
            }
        }

        $textFields = ['comentario_perdido', 'comentario_danado', 'comentario_robado', 'observacion', 'svc', 'zona'];
        foreach ($textFields as $tf) {
            if (!empty($record->{$tf})) {
                $score += 1;
            }
        }

        return $score;
    }
}
