<?php

namespace App\Http\Controllers;

use App\Models\DriverLogisticsRecord;
use App\Models\Location;
use App\Models\TrafficZone;
use App\Models\Transporte;
use App\Models\Transportista;
use App\Services\DriverPayments\DriverLogisticsAutoSyncService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DriverLogisticsRecordController extends Controller
{
    public function index(Request $request): View
    {
        // Restrict access for limited drivers
        $user = auth()->user();
        if ($user && $user->isTransportista() && ! $user->isAdminOrSuper()) {
            abort(403, 'Acceso no autorizado.');
        }

        $fechaDesde = $request->input('fecha_desde', Carbon::now()->startOfMonth()->toDateString());
        $fechaHasta = $request->input('fecha_hasta', Carbon::now()->endOfMonth()->toDateString());
        $selectedCarrierId = $request->input('transportista_id');
        $selectedConcept = $request->input('concepto');

        $carriers = Transportista::active()
            ->select(['id', 'name'])
            ->with(['transportes' => function ($q) {
                $q->where('is_active', true)
                  ->select(['id', 'transportista_id', 'license_plate', 'alias', 'owner_name', 'model', 'type', 'is_default']);
            }])
            ->orderBy('name')
            ->get();

        $activeCarrierIds = $carriers->pluck('id');

        $query = DriverLogisticsRecord::query()
            ->select([
                'id', 'fecha', 'transportista_id', 'transporte_id', 'traffic_zone_id',
                'svc', 'ruta', 'numero', 'zona', 'paradas', 'paquetes', 'entregados',
                'deja_en_svc', 'paq_no_colectado', 'nadie_en_domicilio', 'negocio_cerrado',
                'qr', 'fuera_de_zona', 'zona_inaccesible', 'rechazado', 'sin_visitar',
                'fraude', 'paquete_perdido', 'paquete_danado', 'paquete_robado',
                'comentario_perdido', 'comentario_danado', 'comentario_robado',
                'porcentaje', 'kilometros', 'kilometros_estimados', 'zona_lejana', 'observacion'
            ])
            ->whereIn('transportista_id', $activeCarrierIds)
            ->whereBetween('fecha', [$fechaDesde, $fechaHasta]);

        if ($selectedCarrierId) {
            $query->where('transportista_id', $selectedCarrierId);
        }

        if ($selectedConcept) {
            $query->where('zona', $selectedConcept);
        }

        $records = $query->orderBy('fecha')
            ->orderBy('id')
            ->get();

        $zones = TrafficZone::query()
            ->select(['id', 'name', 'svs_values'])
            ->orderBy('name')
            ->get();

        $locations = Location::active()
            ->select(['id', 'name'])
            ->orderBy('name')
            ->get();

        $paymentConcepts = \App\Models\DriverPaymentConcept::query()
            ->where('active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('traffic.planilla-choferes.index', [
            'records' => $records,
            'carriers' => $carriers,
            'zones' => $zones,
            'locations' => $locations,
            'paymentConcepts' => $paymentConcepts,
            'fechaDesde' => $fechaDesde,
            'fechaHasta' => $fechaHasta,
            'selectedCarrierId' => $selectedCarrierId,
            'selectedConcept' => $selectedConcept,
        ]);
    }

    public function save(Request $request): JsonResponse
    {
        $user = auth()->user();
        if ($user && $user->isTransportista() && ! $user->isAdminOrSuper()) {
            return response()->json(['success' => false, 'message' => 'Acceso no autorizado.'], 403);
        }

        $request->validate([
            'rows' => ['nullable', 'array'],
            'rows.*.id' => ['nullable', 'integer'],
            'rows.*.temp_id' => ['nullable', 'string'],
            'rows.*.fecha' => ['required', 'date'],
            'rows.*.transportista_id' => ['required', 'integer', 'exists:transportistas,id'],
            'rows.*.transporte_id' => ['nullable', 'integer', 'exists:transportes,id'],
            'rows.*.traffic_zone_id' => ['nullable', 'integer', 'exists:traffic_zones,id'],
            'rows.*.svc' => ['nullable', 'string', 'max:255'],
            'rows.*.ruta' => ['nullable', 'string', 'max:255'],
            'rows.*.numero' => ['nullable', 'string', 'max:255'],
            'rows.*.zona' => ['nullable', 'string', 'max:255'],
            'rows.*.paradas' => ['nullable', 'integer', 'min:0'],
            'rows.*.paquetes' => ['nullable', 'integer', 'min:0'],
            'rows.*.entregados' => ['nullable', 'integer', 'min:0'],
            'rows.*.deja_en_svc' => ['nullable', 'integer', 'min:0'],
            'rows.*.paq_no_colectado' => ['nullable', 'integer', 'min:0'],
            'rows.*.nadie_en_domicilio' => ['nullable', 'integer', 'min:0'],
            'rows.*.negocio_cerrado' => ['nullable', 'integer', 'min:0'],
            'rows.*.qr' => ['nullable', 'integer', 'min:0'],
            'rows.*.fuera_de_zona' => ['nullable', 'integer', 'min:0'],
            'rows.*.zona_inaccesible' => ['nullable', 'integer', 'min:0'],
            'rows.*.rechazado' => ['nullable', 'integer', 'min:0'],
            'rows.*.sin_visitar' => ['nullable', 'integer', 'min:0'],
            'rows.*.fraude' => ['nullable', 'integer', 'min:0'],
            'rows.*.paquete_perdido' => ['nullable', 'integer', 'min:0'],
            'rows.*.paquete_danado' => ['nullable', 'integer', 'min:0'],
            'rows.*.paquete_robado' => ['nullable', 'integer', 'min:0'],
            'rows.*.comentario_perdido' => ['nullable', 'string'],
            'rows.*.comentario_danado' => ['nullable', 'string'],
            'rows.*.comentario_robado' => ['nullable', 'string'],
            'rows.*.kilometros' => ['nullable', 'numeric', 'min:0'],
            'rows.*.kilometros_estimados' => ['nullable', 'numeric', 'min:0'],
            'rows.*.porcentaje' => ['nullable', 'numeric', 'min:0'],
            'rows.*.zona_lejana' => ['nullable', 'boolean'],
            'rows.*.observacion' => ['nullable', 'string'],
            'deleted_ids' => ['nullable', 'array'],
            'deleted_ids.*' => ['integer'],
        ]);

        DriverLogisticsAutoSyncService::$isSyncing = true;

        try {
            $deletedIds = $request->input('deleted_ids', []);
            $savedRecordIds = [];

            // Pre-fetch caches for logging to eliminate N+1 queries during loop
            $carrierCache = [];
            $transporteCache = [];

            $getCarrierName = function ($cId) use (&$carrierCache) {
                if (!$cId) return 'N/A';
                if (!isset($carrierCache[$cId])) {
                    $c = Transportista::find($cId);
                    $carrierCache[$cId] = $c ? $c->name : 'N/A';
                }
                return $carrierCache[$cId];
            };

            $getTransporteName = function ($tId) use (&$transporteCache) {
                if (!$tId) return 'N/A';
                if (!isset($transporteCache[$tId])) {
                    $t = Transporte::find($tId);
                    $transporteCache[$tId] = $t ? ($t->alias ?: $t->license_plate) : 'N/A';
                }
                return $transporteCache[$tId];
            };

            $savedRows = DB::transaction(function () use ($request, $user, &$savedRecordIds, $getCarrierName, $getTransporteName) {
                $rows = $request->input('rows', []);
                $deletedIds = $request->input('deleted_ids', []);
                
                $savedList = [];
                $usedRecordIdsInBatch = [];

                // Delete records and log deletion
                if (!empty($deletedIds)) {
                    foreach ($deletedIds as $delId) {
                        $record = DriverLogisticsRecord::find($delId);
                        if ($record) {
                            $carrierName = $getCarrierName($record->transportista_id);
                            $fechaStr = $record->fecha instanceof Carbon ? $record->fecha->format('Y-m-d') : substr($record->fecha, 0, 10);
                            \App\Models\DriverLogisticsRecordLog::create([
                                'driver_logistics_record_id' => $delId,
                                'user_id' => $user ? $user->id : null,
                                'action' => 'eliminar',
                                'details' => "Eliminado: Registro con fecha {$fechaStr} para el chofer {$carrierName}.",
                            ]);
                            $record->delete();
                        }
                    }
                }

                // Save or update records
                foreach ($rows as $rowData) {
                    $id = $rowData['id'] ?? null;
                    $isAutosave = ! empty($rowData['is_autosave']);
                    $explicitZeroEntregados = ! empty($rowData['explicit_zero_entregados']);

                    $record = $id ? DriverLogisticsRecord::find($id) : null;

                    if (! $record && empty($id)) {
                        // Deduplication fallback: check if a record with matching key attributes was created very recently (e.g. concurrent autosave)
                        $recentMatchQuery = DriverLogisticsRecord::query()
                            ->where('fecha', $rowData['fecha'])
                            ->where('transportista_id', $rowData['transportista_id']);

                        if (isset($rowData['traffic_zone_id']) && $rowData['traffic_zone_id']) {
                            $recentMatchQuery->where('traffic_zone_id', $rowData['traffic_zone_id']);
                        } else {
                            $recentMatchQuery->whereNull('traffic_zone_id');
                        }

                        if (isset($rowData['transporte_id']) && $rowData['transporte_id']) {
                            $recentMatchQuery->where('transporte_id', $rowData['transporte_id']);
                        } else {
                            $recentMatchQuery->whereNull('transporte_id');
                        }

                        if (isset($rowData['ruta']) && $rowData['ruta'] !== '') {
                            $recentMatchQuery->where('ruta', $rowData['ruta']);
                        } else {
                            $recentMatchQuery->where(function ($q) {
                                $q->whereNull('ruta')->orWhere('ruta', '');
                            });
                        }

                        if (isset($rowData['numero']) && $rowData['numero'] !== '') {
                            $recentMatchQuery->where('numero', $rowData['numero']);
                        } else {
                            $recentMatchQuery->where(function ($q) {
                                $q->whereNull('numero')->orWhere('numero', '');
                            });
                        }

                        if (! empty($usedRecordIdsInBatch)) {
                            $recentMatchQuery->whereNotIn('id', $usedRecordIdsInBatch);
                        }

                        $recentMatchQuery->where('created_at', '>=', Carbon::now()->subMinutes(2));

                        $recentMatch = $recentMatchQuery->latest('id')->first();
                        if ($recentMatch) {
                            $record = $recentMatch;
                        }
                    }

                    // Parse raw numeric values
                    $rawParadas = array_key_exists('paradas', $rowData) ? $rowData['paradas'] : null;
                    $rawPaquetes = array_key_exists('paquetes', $rowData) ? $rowData['paquetes'] : null;
                    $rawEntregados = array_key_exists('entregados', $rowData) ? $rowData['entregados'] : null;

                    // Resolve paradas
                    if ($record && $rawParadas === null) {
                        $paradas = (int) ($record->paradas ?? 0);
                    } else {
                        $paradas = (int) ($rawParadas ?? 0);
                    }

                    // Resolve paquetes
                    if ($record && $rawPaquetes === null) {
                        $paquetes = (int) ($record->paquetes ?? 0);
                    } else {
                        $paquetes = (int) ($rawPaquetes ?? 0);
                    }

                    // Resolve entregados with anti-erasure protection
                    if ($record && $rawEntregados === null) {
                        $entregados = (int) ($record->entregados ?? 0);
                    } elseif ($record && (int)$rawEntregados === 0 && $isAutosave && (int)$record->entregados > 0 && ! $explicitZeroEntregados) {
                        // Protect existing entregados value from being wiped to 0 during background autosaves
                        $entregados = (int) $record->entregados;
                    } else {
                        $entregados = (int) ($rawEntregados ?? 0);
                    }
                    
                    if (isset($rowData['porcentaje']) && $rowData['porcentaje'] !== '' && $rowData['porcentaje'] !== null) {
                        $porcentaje = (float) $rowData['porcentaje'];
                    } else {
                        $porcentaje = 0;
                        if ($paquetes > 0) {
                            $porcentaje = round(($entregados / $paquetes) * 100, 2);
                        }
                    }

                    $payload = [
                        'fecha' => $rowData['fecha'],
                        'transportista_id' => $rowData['transportista_id'],
                        'transporte_id' => ($rowData['transporte_id'] ?? null) ?: null,
                        'traffic_zone_id' => ($rowData['traffic_zone_id'] ?? null) ?: null,
                        'svc' => ($rowData['svc'] ?? null) ?: null,
                        'ruta' => ($rowData['ruta'] ?? null) ?: null,
                        'numero' => ($rowData['numero'] ?? null) ?: null,
                        'zona' => ($rowData['zona'] ?? null) ?: null,
                        'paradas' => $paradas,
                        'paquetes' => $paquetes,
                        'entregados' => $entregados,
                        'deja_en_svc' => (int) ($rowData['deja_en_svc'] ?? 0),
                        'paq_no_colectado' => (int) ($rowData['paq_no_colectado'] ?? 0),
                        'nadie_en_domicilio' => (int) ($rowData['nadie_en_domicilio'] ?? 0),
                        'negocio_cerrado' => (int) ($rowData['negocio_cerrado'] ?? 0),
                        'qr' => (int) ($rowData['qr'] ?? 0),
                        'fuera_de_zona' => (int) ($rowData['fuera_de_zona'] ?? 0),
                        'zona_inaccesible' => (int) ($rowData['zona_inaccesible'] ?? 0),
                        'rechazado' => (int) ($rowData['rechazado'] ?? 0),
                        'sin_visitar' => (int) ($rowData['sin_visitar'] ?? 0),
                        'fraude' => (int) ($rowData['fraude'] ?? 0),
                        'paquete_perdido' => (int) ($rowData['paquete_perdido'] ?? 0),
                        'paquete_danado' => (int) ($rowData['paquete_danado'] ?? 0),
                        'paquete_robado' => (int) ($rowData['paquete_robado'] ?? 0),
                        'comentario_perdido' => ($rowData['comentario_perdido'] ?? null) ?: null,
                        'comentario_danado' => ($rowData['comentario_danado'] ?? null) ?: null,
                        'comentario_robado' => ($rowData['comentario_robado'] ?? null) ?: null,
                        'porcentaje' => $porcentaje,
                        'kilometros' => (float) ($rowData['kilometros'] ?? 0),
                        'kilometros_estimados' => (float) ($rowData['kilometros_estimados'] ?? 0),
                        'zona_lejana' => filter_var($rowData['zona_lejana'] ?? false, FILTER_VALIDATE_BOOLEAN),
                        'observacion' => ($rowData['observacion'] ?? null) ?: null,
                        'updated_by' => $user ? $user->id : null,
                    ];                    if ($record) {
                        $usedRecordIdsInBatch[] = $record->id;
                        $original = $record->getOriginal();
                        $record->update($payload);
                        $changes = $record->getChanges();

                        // Build detailed log
                        $changedList = [];
                        $labels = [
                            'fecha' => 'Fecha',
                            'transportista_id' => 'Chofer',
                            'transporte_id' => 'Vehículo',
                            'svc' => 'SVC',
                            'ruta' => 'Ruta',
                            'numero' => 'Número',
                            'zona' => 'Zona',
                            'paradas' => 'Paradas',
                            'paquetes' => 'Paquetes',
                            'entregados' => 'Entregados',
                            'deja_en_svc' => 'Deja en SVC',
                            'paq_no_colectado' => 'Paq. NO Col.',
                            'nadie_en_domicilio' => 'Nadie Dom.',
                            'negocio_cerrado' => 'Neg. Cerrado',
                            'qr' => 'QR',
                            'fuera_de_zona' => 'Fuera Zona',
                            'zona_inaccesible' => 'Z. Inaccesible',
                            'rechazado' => 'Rechazado',
                            'sin_visitar' => 'Sin Visitar',
                            'fraude' => 'Fraude',
                            'paquete_perdido' => 'P. Perdido',
                            'paquete_danado' => 'P. Dañado',
                            'paquete_robado' => 'P. Robado',
                            'comentario_perdido' => 'Comentario Perdido',
                            'comentario_danado' => 'Comentario Dañado',
                            'comentario_robado' => 'Comentario Robado',
                            'kilometros' => 'Kilómetros',
                            'kilometros_estimados' => 'Km Est.',
                            'zona_lejana' => 'Lejana',
                            'observacion' => 'Observación',
                        ];

                        foreach ($changes as $key => $newValue) {
                            if (in_array($key, ['updated_by', 'updated_at', 'porcentaje'])) {
                                continue;
                            }
                            $oldValue = $original[$key] ?? 'N/A';
                            if ($key === 'transportista_id') {
                                $oldValue = $getCarrierName($oldValue);
                                $newValue = $getCarrierName($newValue);
                            } elseif ($key === 'transporte_id') {
                                $oldValue = $getTransporteName($oldValue);
                                $newValue = $getTransporteName($newValue);
                            } elseif ($key === 'zona_lejana') {
                                $oldValue = $oldValue ? 'Sí' : 'No';
                                $newValue = $newValue ? 'Sí' : 'No';
                            }
                            $label = $labels[$key] ?? $key;
                            $changedList[] = "{$label}: de '{$oldValue}' a '{$newValue}'";
                        }

                        if (!empty($changedList)) {
                            \App\Models\DriverLogisticsRecordLog::create([
                                'driver_logistics_record_id' => $record->id,
                                'user_id' => $user ? $user->id : null,
                                'action' => 'modificar',
                                'details' => "Modificado: " . implode(', ', $changedList),
                            ]);
                        }
                    } else {
                        $payload['created_by'] = $user ? $user->id : null;
                        $record = DriverLogisticsRecord::create($payload);
                        $usedRecordIdsInBatch[] = $record->id;
                        $carrierName = $getCarrierName($record->transportista_id);
                        $fechaStr = $record->fecha instanceof Carbon ? $record->fecha->format('Y-m-d') : substr($record->fecha, 0, 10);
                        \App\Models\DriverLogisticsRecordLog::create([
                            'driver_logistics_record_id' => $record->id,
                            'user_id' => $user ? $user->id : null,
                            'action' => 'crear',
                            'details' => "Creado: Registro con fecha {$fechaStr} para el chofer {$carrierName}.",
                        ]);
                    }

                    $savedRecordIds[] = $record->id;

                    $savedList[] = [
                        'temp_id' => $rowData['temp_id'] ?? null,
                        'id' => $record->id,
                    ];
                }

                return $savedList;
            });

            // Automatically reimport/sync modified logistics records into driver payment receipts ONCE for the entire batch
            app(DriverLogisticsAutoSyncService::class)
                ->syncByRecordIds($savedRecordIds, $deletedIds);

            return response()->json([
                'success' => true,
                'message' => 'Planilla guardada exitosamente.',
                'saved_rows' => $savedRows,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al guardar la planilla: ' . $e->getMessage(),
            ], 422);
        } finally {
            DriverLogisticsAutoSyncService::$isSyncing = false;
        }
    }

    public function logs($id): JsonResponse
    {
        $logs = \App\Models\DriverLogisticsRecordLog::query()
            ->with('user')
            ->where('driver_logistics_record_id', $id)
            ->latest()
            ->get();

        $formatted = $logs->map(function ($log) {
            return [
                'user' => $log->user ? $log->user->name : 'Sistema',
                'action' => ucfirst($log->action),
                'details' => $log->details,
                'fecha' => $log->created_at->format('d/m/Y H:i:s'),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $formatted,
        ]);
    }

    public function getConcepts(): JsonResponse
    {
        $user = auth()->user();
        if ($user && $user->isTransportista() && ! $user->isAdminOrSuper()) {
            return response()->json(['error' => 'Acceso no autorizado.'], 403);
        }

        $concepts = \App\Models\DriverPaymentConcept::query()
            ->orderBy('name')
            ->get();

        return response()->json($concepts);
    }

    public function getCarriers(): JsonResponse
    {
        $user = auth()->user();
        if ($user && $user->isTransportista() && ! $user->isAdminOrSuper()) {
            return response()->json(['error' => 'Acceso no autorizado.'], 403);
        }

        $carriers = Transportista::with(['transportes' => function ($q) {
            $q->where('is_active', true);
        }])->orderBy('name')->get();

        return response()->json($carriers);
    }

    public function cleanDuplicates(Request $request): JsonResponse
    {
        $user = auth()->user();
        if ($user && $user->isTransportista() && ! $user->isAdminOrSuper()) {
            return response()->json(['success' => false, 'message' => 'Acceso no autorizado.'], 403);
        }

        $fecha = $request->input('fecha');
        $fechaDesde = $request->input('fecha_desde');
        $fechaHasta = $request->input('fecha_hasta');
        $isDryRun = filter_var($request->input('dry_run', false), FILTER_VALIDATE_BOOLEAN);

        $result = \App\Console\Commands\CleanDuplicateDriverLogisticsRecords::cleanDuplicates($fecha, $isDryRun, $fechaDesde, $fechaHasta);

        return response()->json([
            'success' => true,
            'message' => $isDryRun 
                ? "Simulación completada. Grupos con duplicados: {$result['duplicate_groups_count']}. Registros que se eliminarían: {$result['total_deleted_count']}."
                : "Limpieza realizada con éxito. Grupos corregidos: {$result['duplicate_groups_count']}. Registros duplicados eliminados: {$result['total_deleted_count']}.",
            'data' => $result,
        ]);
    }

    public function destroy(DriverLogisticsRecord $record): JsonResponse
    {
        $user = auth()->user();
        if ($user && $user->isTransportista() && ! $user->isAdminOrSuper()) {
            return response()->json(['success' => false, 'message' => 'Acceso no autorizado.'], 403);
        }

        $carrierName = $record->transportista ? $record->transportista->name : 'N/A';
        $fechaStr = $record->fecha instanceof Carbon ? $record->fecha->format('Y-m-d') : substr((string)$record->fecha, 0, 10);

        \App\Models\DriverLogisticsRecordLog::create([
            'driver_logistics_record_id' => $record->id,
            'user_id' => $user ? $user->id : null,
            'action' => 'eliminar',
            'details' => "Eliminado directamente: Registro con fecha {$fechaStr} para el chofer {$carrierName}.",
        ]);

        $recordId = $record->id;
        $record->delete();

        app(\App\Services\DriverPayments\DriverLogisticsAutoSyncService::class)
            ->syncByRecordIds([], [$recordId]);

        return response()->json([
            'success' => true,
            'message' => 'El registro ha sido eliminado por completo de la base de datos.',
        ]);
    }
}
