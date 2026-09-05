<?php

namespace App\Http\Controllers;

use App\Models\DriverLogisticsRecord;
use App\Models\Location;
use App\Models\TrafficZone;
use App\Models\Transporte;
use App\Models\Transportista;
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

        $query = DriverLogisticsRecord::query()
            ->whereHas('transportista', function ($q) {
                $q->active();
            })
            ->with(['transportista', 'transporte'])
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

        $carriers = Transportista::active()
            ->with(['transportes' => function ($q) {
                $q->where('is_active', true);
            }])
            ->orderBy('name')
            ->get();

        $zones = TrafficZone::query()->orderBy('name')->get();
        $locations = Location::active()->orderBy('name')->get();
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

        try {
            $savedRows = DB::transaction(function () use ($request, $user) {
                $rows = $request->input('rows', []);
                $deletedIds = $request->input('deleted_ids', []);
                
                $savedList = [];
                $usedRecordIdsInBatch = [];

                // Delete records and log deletion
                if (!empty($deletedIds)) {
                    foreach ($deletedIds as $delId) {
                        $record = DriverLogisticsRecord::find($delId);
                        if ($record) {
                            $carrierName = $record->transportista ? $record->transportista->name : 'N/A';
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
                    
                    // Parse values with fallback defaults
                    $paquetes = (int) ($rowData['paquetes'] ?? 0);
                    $entregados = (int) ($rowData['entregados'] ?? 0);
                    
                    if (isset($rowData['porcentaje']) && $rowData['porcentaje'] !== '') {
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
                        'paradas' => (int) ($rowData['paradas'] ?? 0),
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
                    ];

                    $record = $id ? DriverLogisticsRecord::find($id) : null;

                    if (! $record && empty($id)) {
                        // Deduplication fallback: check if a record with matching key attributes was created very recently (e.g. concurrent autosave)
                        $recentMatchQuery = DriverLogisticsRecord::query()
                            ->where('fecha', $payload['fecha'])
                            ->where('transportista_id', $payload['transportista_id']);

                        if (isset($payload['traffic_zone_id'])) {
                            $recentMatchQuery->where('traffic_zone_id', $payload['traffic_zone_id']);
                        } else {
                            $recentMatchQuery->whereNull('traffic_zone_id');
                        }

                        if (isset($payload['transporte_id'])) {
                            $recentMatchQuery->where('transporte_id', $payload['transporte_id']);
                        } else {
                            $recentMatchQuery->whereNull('transporte_id');
                        }

                        if (isset($payload['ruta']) && $payload['ruta'] !== '') {
                            $recentMatchQuery->where('ruta', $payload['ruta']);
                        } else {
                            $recentMatchQuery->where(function ($q) {
                                $q->whereNull('ruta')->orWhere('ruta', '');
                            });
                        }

                        if (isset($payload['numero']) && $payload['numero'] !== '') {
                            $recentMatchQuery->where('numero', $payload['numero']);
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

                    if ($record) {
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
                                $oldCarrier = \App\Models\Transportista::find($oldValue);
                                $newCarrier = \App\Models\Transportista::find($newValue);
                                $oldValue = $oldCarrier ? $oldCarrier->name : 'N/A';
                                $newValue = $newCarrier ? $newCarrier->name : 'N/A';
                            } elseif ($key === 'transporte_id') {
                                $oldTrans = \App\Models\Transporte::find($oldValue);
                                $newTrans = \App\Models\Transporte::find($newValue);
                                $oldValue = $oldTrans ? ($oldTrans->alias ?: $oldTrans->license_plate) : 'N/A';
                                $newValue = $newTrans ? ($newTrans->alias ?: $newTrans->license_plate) : 'N/A';
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
                        $carrierName = $record->transportista ? $record->transportista->name : 'N/A';
                        $fechaStr = $record->fecha instanceof Carbon ? $record->fecha->format('Y-m-d') : substr($record->fecha, 0, 10);
                        \App\Models\DriverLogisticsRecordLog::create([
                            'driver_logistics_record_id' => $record->id,
                            'user_id' => $user ? $user->id : null,
                            'action' => 'crear',
                            'details' => "Creado: Registro con fecha {$fechaStr} para el chofer {$carrierName}.",
                        ]);
                    }

                    $savedList[] = [
                        'temp_id' => $rowData['temp_id'] ?? null,
                        'id' => $record->id,
                    ];
                }

                return $savedList;
            });

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
        $isDryRun = filter_var($request->input('dry_run', false), FILTER_VALIDATE_BOOLEAN);

        $result = \App\Console\Commands\CleanDuplicateDriverLogisticsRecords::cleanDuplicates($fecha, $isDryRun);

        return response()->json([
            'success' => true,
            'message' => $isDryRun 
                ? "Simulación completada. Grupos con duplicados: {$result['duplicate_groups_count']}. Registros que se eliminarían: {$result['total_deleted_count']}."
                : "Limpieza realizada con éxito. Grupos corregidos: {$result['duplicate_groups_count']}. Registros duplicados eliminados: {$result['total_deleted_count']}.",
            'data' => $result,
        ]);
    }

    public function cleanDuplicatesGet(Request $request)
    {
        $user = auth()->user();
        if ($user && $user->isTransportista() && ! $user->isAdminOrSuper()) {
            abort(403, 'Acceso no autorizado.');
        }

        $fecha = $request->input('fecha');
        $isDryRun = filter_var($request->input('dry_run', false), FILTER_VALIDATE_BOOLEAN);

        $result = \App\Console\Commands\CleanDuplicateDriverLogisticsRecords::cleanDuplicates($fecha, $isDryRun);

        $statusText = $isDryRun ? 'Simulación Realizada (Dry Run)' : 'Limpieza de Duplicados Ejecutada Exitosamente';
        $alertClass = $isDryRun ? 'warning' : 'success';
        $backUrl = route('traffic.planilla-choferes.index');

        $html = "
        <!DOCTYPE html>
        <html lang='es'>
        <head>
            <meta charset='UTF-8'>
            <title>Limpieza de Registros Duplicados</title>
            <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' rel='stylesheet'>
        </head>
        <body class='bg-light py-5'>
            <div class='container' style='max-width: 650px;'>
                <div class='card shadow border-0'>
                    <div class='card-header bg-{$alertClass} text-white py-3'>
                        <h4 class='mb-0 fw-bold'><i class='fa-solid fa-broom me-2'></i>{$statusText}</h4>
                    </div>
                    <div class='card-body p-4'>
                        <p class='lead mb-4'>Proceso completado para los registros de rendición de la planilla de choferes.</p>
                        <div class='list-group mb-4'>
                            <div class='list-group-item d-flex justify-content-between align-items-center'>
                                <span>Grupos de registros duplicados encontrados</span>
                                <span class='badge bg-primary rounded-pill fs-6'>{$result['duplicate_groups_count']}</span>
                            </div>
                            <div class='list-group-item d-flex justify-content-between align-items-center'>
                                <span>Total de registros duplicados eliminados</span>
                                <span class='badge bg-danger rounded-pill fs-6'>{$result['total_deleted_count']}</span>
                            </div>
                        </div>
                        <div class='d-grid gap-2'>
                            <a href='{$backUrl}' class='btn btn-primary btn-lg'>Volver a Planilla de Choferes</a>
                        </div>
                    </div>
                </div>
            </div>
        </body>
        </html>
        ";

        return response($html);
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

        $record->delete();

        return response()->json([
            'success' => true,
            'message' => 'El registro ha sido eliminado por completo de la base de datos.',
        ]);
    }
}
