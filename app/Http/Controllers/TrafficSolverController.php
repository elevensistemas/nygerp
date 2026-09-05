<?php

namespace App\Http\Controllers;

use App\Models\Party;
use App\Models\TrafficLooseStop;
use App\Models\Transportista;
use App\Models\Location;
use App\Services\RouteSolver;
use App\Services\Geocoder;
use App\Services\TrafficRouteService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\IOFactory;

class TrafficSolverController extends Controller
{
    public function index(Request $request)
    {
        $this->guardTransportista($request);

        $transportistasList = Transportista::withCount('transportes')
            ->orderBy('name')
            ->get();
        $selectedIds = $transportistasList
            ->where('transportes_count', '>', 0)
            ->pluck('id')
            ->values()
            ->all();
        $transportistas = Transportista::with(['transportes', 'zones', 'sharedZones'])
            ->whereIn('id', $selectedIds)
            ->get()
            ->values();
        $locations = Location::active()->orderBy('name')->get();
        $customers = Party::where('role', 'customer')
            ->orderBy('business_name')
            ->orderBy('name')
            ->get();

        return view('traffic.solver.index', [
            'transportistas' => $transportistas,
            'transportistasList' => $transportistasList,
            'selectedTransportistas' => $selectedIds,
            'uploadedStops' => collect(),
            'previewPlans' => collect(),
            'maxStops' => 15,
            'balancedLoad' => false,
            'strategy' => 'route',
            'mapProvider' => 'openstreet',
            'warnings' => [],
            'plansPayload' => [],
            'locations' => $locations,
            'selectedLocation' => null,
            'returnToDepot' => false,
            'avoidTolls' => false,
            'avoidHighways' => false,
            'customers' => $customers,
            'selectedCustomer' => null,
        ]);
    }

    public function preview(Request $request)
    {
        $this->guardTransportista($request);

        if ($request->isMethod('get')) {
            return redirect()->route('traffic.solver.index');
        }

        $data = $request->validate([
            'file' => 'nullable|file|mimes:xlsx,xls,csv,txt',
            'max_stops' => 'required|integer|min:1',
            'strategy' => 'required|string|in:route,cost,weight',
            'map_provider' => 'required|string|in:openstreet,mapbox,google',
            'cluster_by' => 'nullable|string|in:address,priority,none',
            'balanced_load' => ['nullable', 'boolean'],
            'clustering' => 'nullable|string|in:none,address,priority',
            'avoid_tolls' => ['nullable', 'boolean'],
            'avoid_highways' => ['nullable', 'boolean'],
            'sheet' => 'nullable|string',
            'manual_addresses' => 'nullable|string',
            'solver_action' => 'nullable|string|in:validate,solve',
            'transportista_ids' => ['nullable', 'array'],
            'transportista_ids.*' => ['integer', 'exists:transportistas,id'],
            'party_id' => ['required_with:file', 'nullable', 'integer', 'exists:parties,id'],
        ]);

        $balancedLoad = filter_var($data['balanced_load'] ?? false, FILTER_VALIDATE_BOOLEAN);

        $transportistasList = Transportista::withCount('transportes')
            ->orderBy('name')
            ->get();
        $defaultSelectedIds = $transportistasList
            ->where('transportes_count', '>', 0)
            ->pluck('id')
            ->values()
            ->all();
        $selectedIds = $request->has('transportista_ids')
            ? array_values(array_filter(array_map('intval', (array) $request->input('transportista_ids', []))))
            : $defaultSelectedIds;

        $transportistas = Transportista::with(['transportes', 'zones', 'sharedZones'])
            ->whereHas('transportes')
            ->when(count($selectedIds), function ($query) use ($selectedIds) {
                $query->whereIn('id', $selectedIds);
            })
            ->get()
            ->values();
        if ($transportistas->isEmpty()) {
            return back()->withInput()->with('info', 'Selecciona al menos un transportista con transporte.');
        }

        $locations = Location::active()->orderBy('name')->get();
        $customers = Party::where('role', 'customer')
            ->orderBy('business_name')
            ->orderBy('name')
            ->get();
        $manualPayload = $request->input('manual_addresses');
        $hasManual = !empty($manualPayload);
        $hasFile = $request->hasFile('file');
        if (! $hasFile && ! $hasManual) {
            return back()->withInput()->with('info', 'Carga un archivo o corrige las direcciones para continuar.');
        }

        $extension = $hasFile
            ? strtolower((string) $request->file('file')->getClientOriginalExtension())
            : null;
        $canUseSpreadsheet = class_exists(IOFactory::class);
        if ($hasFile && in_array($extension, ['xlsx', 'xls'], true) && !$canUseSpreadsheet) {
            return back()->withInput()->with('info', 'No se pudo leer el archivo Excel.');
        }

        if ($hasManual) {
            $decoded = json_decode($manualPayload, true);
            if (!is_array($decoded)) {
                return back()->withInput()->with('info', 'No se pudieron leer las direcciones corregidas.');
            }
            $uploadedStops = collect($decoded)->map(function ($row, $index) {
                return [
                    'code' => $row['code'] ?? ('PED-' . str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT)),
                    'address' => trim((string) ($row['address'] ?? '')),
                    'priority' => $row['priority'] ?? 'Media',
                    'lat' => $row['lat'] ?? null,
                    'lng' => $row['lng'] ?? null,
                    'notes' => $row['notes'] ?? '',
                    'loose_stop_id' => $row['loose_stop_id'] ?? null,
                    'cluster_id' => $row['cluster_id'] ?? null,
                    'party_id' => $row['party_id'] ?? null,
                    'weight_actual' => isset($row['weight_actual']) ? (float) $row['weight_actual'] : null,
                    'weight_volumetric' => isset($row['weight_volumetric']) ? (float) $row['weight_volumetric'] : null,
                    'length' => isset($row['length']) ? (float) $row['length'] : null,
                    'width' => isset($row['width']) ? (float) $row['width'] : null,
                    'height' => isset($row['height']) ? (float) $row['height'] : null,
                ];
            })->filter(fn ($row) => !empty($row['address']))->values();
        } else {
            $uploadedStops = $this->parseUploadedStops($request, $data['sheet'] ?? null);
        }

        if ($uploadedStops->isEmpty()) {
            return back()->withInput()->with('info', 'No se encontraron direcciones en el archivo.');
        }

        $geocoder = new Geocoder();
        [$geocodedStops, $geoWarnings] = $geocoder->geocodeStops($uploadedStops, $data['map_provider'], true, true);
        
        \Log::info('TrafficSolverController: preview() after geocoding', [
            'geocodedStops_count' => $geocodedStops->count(),
            'first_stop_before_persist' => $geocodedStops->count() > 0 ? $geocodedStops[0] : null,
        ]);
        
        $geocodedStops = $this->persistLooseStops(
            $geocodedStops,
            $request->input('party_id'),
            $request->user(),
            $request->file('file')
        );
        
        \Log::info('TrafficSolverController: preview() after persistLooseStops', [
            'geocodedStops_count' => $geocodedStops->count(),
            'party_id' => $request->input('party_id'),
            'first_stop_after_persist' => $geocodedStops->count() > 0 ? $geocodedStops[0] : null,
        ]);
        $geocodedStops = $this->enrichStopLoads($geocodedStops);
        $validStops = $geocodedStops->filter(function ($stop) {
            $lat = $stop['lat'] ?? null;
            $lng = $stop['lng'] ?? null;
            return is_numeric($lat) && is_numeric($lng);
        })->values();
        $invalidStops = $geocodedStops->filter(function ($stop) {
            $lat = $stop['lat'] ?? null;
            $lng = $stop['lng'] ?? null;
            return !is_numeric($lat) || !is_numeric($lng);
        })->values();
        $requestedMaxStops = max(1, (int) ($data['max_stops'] ?? 1));
        $solver = new RouteSolver();
        $eligibleTransportistas = $balancedLoad
            ? $solver->eligibleTransportistas($transportistas, $validStops)
            : $transportistas;
        $eligibleCount = max(1, $eligibleTransportistas->count());
        $maxStops = $balancedLoad
            ? max(1, (int) ceil(max(1, $validStops->count()) / $eligibleCount))
            : $requestedMaxStops;
        $action = $data['solver_action'] ?? 'validate';

        if ($invalidStops->isNotEmpty()) {
            $geoWarnings[] = 'Hay direcciones invalidas. Corrigelas para continuar.';
        }

        if ($action !== 'solve' || $invalidStops->isNotEmpty()) {
            return view('traffic.solver.index', [
                'transportistas' => $transportistas,
                'transportistasList' => $transportistasList,
                'selectedTransportistas' => $selectedIds,
                'uploadedStops' => $geocodedStops,
                'previewPlans' => collect(),
                'maxStops' => $maxStops,
                'strategy' => $data['strategy'],
                'mapProvider' => $data['map_provider'],
                'warnings' => $geoWarnings,
                'plansPayload' => [],
                'locations' => $locations,
                'selectedLocation' => $request->input('location_id'),
                'returnToDepot' => $request->boolean('return_to_depot'),
                'avoidTolls' => $request->boolean('avoid_tolls'),
                'avoidHighways' => $request->boolean('avoid_highways'),
                'customers' => $customers,
                'selectedCustomer' => $request->input('party_id'),
                'balancedLoad' => $balancedLoad,
            ]);
        }

        $clusterBy = (string) ($data['cluster_by'] ?? ($data['clustering'] ?? 'none'));
        $result = $solver->solve(
            $transportistas,
            $geocodedStops,
            $maxStops,
            $data['strategy'],
            $clusterBy
        );
        $allWarnings = array_merge($geoWarnings, $result['warnings']);
        
        // Convertir a arrays indexados para fácil búsqueda
        $geocodedStopsArray = $geocodedStops->values()->toArray();
        
        \Log::info('TrafficSolverController: preview() enrichment start', [
            'geocodedStops_count' => $geocodedStops->count(),
            'geocodedStopsArray_count' => count($geocodedStopsArray),
            'first_geocoded_stop' => count($geocodedStopsArray) > 0 ? $geocodedStopsArray[0] : null,
            'result_plans_count' => count($result['plans'] ?? []),
            'first_plan_stops_count' => count($result['plans'] ?? []) > 0 ? count($result['plans'][0]['stops'] ?? []) : 0,
        ]);
        
        // Mapear loose_stop_id desde los stops geocodeados originales
        $enrichedPlans = $result['plans']->map(function ($plan) use ($geocodedStopsArray) {
            $enrichedStops = [];
            foreach ($plan['stops'] as $stop) {
                // Buscar el stop original por dirección y coordenadas para obtener loose_stop_id
                foreach ($geocodedStopsArray as $original) {
                    if (
                        abs((float)$original['lat'] - (float)$stop['lat']) < 0.00001 &&
                        abs((float)$original['lng'] - (float)$stop['lng']) < 0.00001 &&
                        strtolower(trim($original['address'])) === strtolower(trim($stop['address']))
                    ) {
                        $stop['loose_stop_id'] = $original['loose_stop_id'] ?? null;
                        break;
                    }
                }
                $enrichedStops[] = $stop;
            }
            
            // Mantener stops como array simple
            $plan['stops'] = $enrichedStops;
            
            // Convertir carrier a array para JSON serialization en Blade
            if (is_object($plan['carrier'])) {
                // Obtener el transporte default de este transportista
                $defaultTransporte = $plan['carrier']->defaultTransporte();
                
                $plan['carrier'] = [
                    'id' => $plan['carrier']->id,
                    'name' => $plan['carrier']->name,
                    'cost_efficiency' => $plan['carrier']->cost_efficiency ?? 1,
                    'performance_weight' => $plan['carrier']->performance_weight ?? 1,
                    'color' => $plan['carrier']->color ?? '#2563eb',
                ];
                
                // Agregar el transporte default si existe
                if ($defaultTransporte) {
                    $plan['transporte'] = [
                        'id' => $defaultTransporte->id,
                        'alias' => $defaultTransporte->alias,
                        'license_plate' => $defaultTransporte->license_plate,
                        'type' => $defaultTransporte->type,
                        'capacity_kg' => $defaultTransporte->capacity_kg,
                        'is_default' => true,
                    ];
                }
            }
            
            return $plan;
        })->toArray();
        
        \Log::info('TrafficSolverController: preview() enrichment end', [
            'enrichedPlans_count' => count($enrichedPlans),
            'first_plan_stops_count' => count($enrichedPlans) > 0 ? count($enrichedPlans[0]['stops'] ?? []) : 0,
            'first_stop' => count($enrichedPlans) > 0 && count($enrichedPlans[0]['stops'] ?? []) > 0 ? $enrichedPlans[0]['stops'][0] : null,
        ]);

        $routeService = new TrafficRouteService();
        $mapboxOptions = [
            'avoid_tolls' => $request->boolean('avoid_tolls'),
            'avoid_highways' => $request->boolean('avoid_highways'),
        ];
        foreach ($enrichedPlans as &$plan) {
            $stops = $plan['stops'] ?? [];
            if (!is_array($stops) || count($stops) < 2) {
                continue;
            }
            try {
                $optimized = $routeService->optimizeStopOrder($stops, $mapboxOptions);
                $plan['stops'] = $optimized['stops'];
                $plan['mapbox_trip'] = $optimized['trip'];
                if (!empty($optimized['warning'])) {
                    $plan['mapbox_warning'] = $optimized['warning'];
                }
            } catch (\Throwable $e) {
                Log::warning('TrafficSolverController::preview Mapbox reorder failed', [
                    'message' => $e->getMessage(),
                    'plan_carrier' => $plan['carrier'] ?? $plan['carrier_id'] ?? null,
                ]);
            }
        }
        unset($plan);

        $plansPayload = $this->plainPlans($enrichedPlans);

        return view('traffic.solver.index', [
            'transportistas' => $transportistas,
            'transportistasList' => $transportistasList,
            'selectedTransportistas' => $selectedIds,
            'uploadedStops' => $geocodedStops,
            'previewPlans' => $enrichedPlans,
            'maxStops' => $maxStops,
            'strategy' => $data['strategy'],
            'mapProvider' => $data['map_provider'],
            'warnings' => $allWarnings,
            'plansPayload' => $plansPayload,
            'locations' => $locations,
            'selectedLocation' => $request->input('location_id'),
            'returnToDepot' => $request->boolean('return_to_depot'),
            'avoidTolls' => $request->boolean('avoid_tolls'),
            'avoidHighways' => $request->boolean('avoid_highways'),
            'customers' => $customers,
            'selectedCustomer' => $request->input('party_id'),
            'balancedLoad' => $balancedLoad,
        ]);
    }

    public function previewFile(Request $request): JsonResponse
    {
        $this->guardTransportista($request);

        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv,txt',
        ]);

        $file = $request->file('file');
        $extension = strtolower((string) $file->getClientOriginalExtension());
        $canUseSpreadsheet = class_exists(IOFactory::class);

        if (in_array($extension, ['xlsx', 'xls'], true) && !$canUseSpreadsheet) {
            return response()->json([
                'error' => 'No se pudo leer el archivo Excel.',
                'columns' => [],
                'sheets' => [],
            ], 422);
        }

        return response()->json(
            $this->loadWorkbookPreview($file->getRealPath(), $extension)
        );
    }

    public function generate(Request $request, TrafficRouteService $routeService)
    {
        $this->guardTransportista($request);
        $payload = json_decode($request->input('plans', '[]'), true);
        
        \Log::info('TrafficSolverController: generate() received payload', [
            'payload_count' => count($payload),
            'first_payload_stops_count' => count($payload) > 0 ? count($payload[0]['stops'] ?? []) : 0,
            'first_stop_loose_id' => count($payload) > 0 && count($payload[0]['stops'] ?? []) > 0 ? ($payload[0]['stops'][0]['loose_stop_id'] ?? 'KEY_NOT_FOUND') : 'NO_STOPS',
            'first_stop_keys' => count($payload) > 0 && count($payload[0]['stops'] ?? []) > 0 ? array_keys($payload[0]['stops'][0]) : [],
            'first_stop_full' => count($payload) > 0 && count($payload[0]['stops'] ?? []) > 0 ? $payload[0]['stops'][0] : [],
        ]);
        
        $returnToDepot = $request->boolean('return_to_depot');
        $locationId = $request->input('location_id');
        $scheduledDateInput = $request->input('scheduled_date');

        $scheduledDate = null;
        if (!empty($scheduledDateInput)) {
            $scheduledDate = Carbon::parse($scheduledDateInput)->startOfDay();
        }

        if (!is_array($payload) || empty($payload)) {
            return back()->with('info', 'No hay planes para generar rutas.');
        }
        
        $created = collect();
        $errors = [];
        $userId = $request->user()->id;

        foreach ($payload as $plan) {
            $carrierId = $plan['carrier_id'] ?? null;
            $stops = $plan['stops'] ?? [];

            if (! $carrierId || count($stops) < 2) {
                $errors[] = 'Plan sin transportista o con menos de 2 paradas.';
                continue;
            }

            /** @var Transportista|null $carrier */
            $carrier = Transportista::with('transportes')->find($carrierId);
            if (! $carrier) {
                $errors[] = "Transportista {$carrierId} no encontrado.";
                continue;
            }

            $vehicle = $carrier->transportes()->where('is_active', true)->first()
                ?? $carrier->transportes()->first();

            // Si se especificó un transporte en el plan, usarlo
            if (!empty($plan['transporte_id'])) {
                $specifiedVehicle = $carrier->transportes()->where('id', $plan['transporte_id'])->first();
                if ($specifiedVehicle) {
                    $vehicle = $specifiedVehicle;
                }
            }

            if (! $vehicle) {
                $errors[] = "Transportista {$carrier->name} sin transporte asignado.";
                continue;
            }

            $orderedStops = [];
            $looseStopIds = collect();
            foreach ($stops as $stop) {
                if (!isset($stop['lat'], $stop['lng']) || !is_numeric($stop['lat']) || !is_numeric($stop['lng'])) {
                    continue;
                }
                if (!empty($stop['loose_stop_id'])) {
                    $looseStopIds->push((int) $stop['loose_stop_id']);
                }
                $orderedStops[] = [
                    'address' => $stop['address'] ?? $stop['code'] ?? 'Direccion',
                    'latitude' => (float) $stop['lat'],
                    'longitude' => (float) $stop['lng'],
                    'label' => $stop['code'] ?? null,
                    'notes' => $stop['priority'] ?? null,
                    'traffic_loose_stop_id' => $stop['loose_stop_id'] ?? null,
                ];
            }
            
            \Log::info('TrafficSolverController: generate() building orderedStops', [
                'carrier_id' => $carrierId,
                'stops_from_payload_count' => count($stops),
                'looseStopIds_count' => $looseStopIds->count(),
                'looseStopIds' => $looseStopIds->toArray(),
                'orderedStops_count' => count($orderedStops),
                'first_orderedStop_traffic_loose_stop_id' => count($orderedStops) > 0 ? ($orderedStops[0]['traffic_loose_stop_id'] ?? 'NULL') : 'NO_STOPS',
            ]);

            if ($returnToDepot && $locationId) {
                $location = Location::active()->find($locationId);
                if (! $location) {
                    $errors[] = 'Depósito no encontrado o inactivo.';
                    continue;
                }
                if ($location->latitude === null || $location->longitude === null) {
                    $errors[] = "Depósito {$location->name} sin coordenadas.";
                    continue;
                }
                $orderedStops[] = [
                    'address' => $location->address,
                    'latitude' => (float) $location->latitude,
                    'longitude' => (float) $location->longitude,
                    'label' => $location->name,
                    'notes' => 'Regreso a depósito',
                ];
            }

            if (count($orderedStops) < 2) {
                $errors[] = "Transportista {$carrier->name}: no hay paradas con coordenadas suficientes.";
                continue;
            }

            try {
                $route = $routeService->createRouteFromSolver($carrier, $vehicle, $userId, $orderedStops, $scheduledDate, [
                    'avoid_tolls' => $request->boolean('avoid_tolls'),
                    'avoid_highways' => $request->boolean('avoid_highways'),
                ]);
                if ($route) {
                    $created->push($route);
                } else {
                    $errors[] = "No se pudo crear la ruta para {$carrier->name}: resultado vacio.";
                }
            } catch (\Throwable $e) {
                \Log::warning('TrafficSolverController: generate() exception', [
                    'carrier_id' => $carrier->id,
                    'carrier_name' => $carrier->name,
                    'message' => $e->getMessage(),
                ]);
                $errors[] = "Error al generar ruta para {$carrier->name}: {$e->getMessage()}";
            }
        }
        \Log::info('TrafficSolverController: generate() result summary', [
            'created_count' => $created->count(),
            'errors_count' => count($errors),
            'errors' => $errors,
        ]);
        if ($errors && $created->isEmpty()) {
            return back()->with('info', implode(' ', $errors));
        }

        $message = "Se generaron {$created->count()} rutas.";
        if ($errors) {
            $message .= ' Advertencias: ' . implode(' ', $errors);
        }

        return redirect()->route('traffic.routes.index')->with('ok', $message);
    }

    private function guardTransportista(Request $request): void
    {
        $user = $request->user();
        if ($user && $user->isTransportista() && ! $user->isAdminOrSuper()) {
            abort(403, 'Acceso no disponible para transportistas.');
        }
    }

    private function parseUploadedStops(Request $request, ?string $sheet = null): Collection
    {
        $file = $request->file('file');
        $rows = [];
        $extension = strtolower($file->getClientOriginalExtension());
        $canUseSpreadsheet = class_exists(IOFactory::class);

        if ($canUseSpreadsheet && in_array($extension, ['xlsx', 'xls'])) {
            $spreadsheet = IOFactory::load($file->getRealPath());
            $worksheet = null;
            if ($sheet !== null && $sheet !== '') {
                if (is_numeric($sheet)) {
                    $index = (int) $sheet;
                    if ($index >= 0 && $index < $spreadsheet->getSheetCount()) {
                        $worksheet = $spreadsheet->getSheet($index);
                    }
                } else {
                    $worksheet = $spreadsheet->getSheetByName($sheet);
                }
            }
            $worksheet = $worksheet ?: $spreadsheet->getActiveSheet();
            $rows = $worksheet->toArray(null, true, true, true);
        } else {
            if (($handle = fopen($file->getRealPath(), 'r')) !== false) {
                while (($row = fgetcsv($handle, 0, ',')) !== false) {
                    $rows[] = $row;
                }
                fclose($handle);
            }
        }

        $stops = collect();
        $rowNumber = 0;

        foreach ($rows as $row) {
            $rowNumber++;
            // datos comienzan en la fila 5 del archivo
            if ($rowNumber <= 4) {
                continue;
            }

            if (is_array($row) && array_key_exists('D', $row)) {
                $code = trim((string) ($row['A'] ?? ''));
                $notes = trim((string) ($row['C'] ?? ''));
                $addressText = trim((string) ($row['D'] ?? ''));
                $localityText = trim((string) ($row['E'] ?? ''));

                if (!$addressText) {
                    continue;
                }

                $fullAddress = $localityText ? ($addressText . ', ' . $localityText) : $addressText;

                $stops->push([
                    'code' => $code ?: 'PED-' . str_pad((string) ($stops->count() + 1), 3, '0', STR_PAD_LEFT),
                    'address' => $fullAddress,
                    'priority' => 'Media',
                    'lat' => $row['lat'] ?? $row['latitude'] ?? null,
                    'lng' => $row['lng'] ?? $row['longitude'] ?? null,
                    'notes' => $notes,
                ]);
            } else {
                $row = array_map('trim', is_array($row) ? array_values($row) : []);
                if (!count($row)) {
                    continue;
                }
                $code = $row[0] ?? '';
                $notes = $row[2] ?? '';
                $addressText = $row[3] ?? '';
                $localityText = $row[4] ?? '';

                if (!trim((string) $addressText)) {
                    continue;
                }
                $fullAddress = $localityText ? ($addressText . ', ' . $localityText) : $addressText;

                $stops->push([
                    'code' => $code ?: 'PED-' . str_pad((string) ($stops->count() + 1), 3, '0', STR_PAD_LEFT),
                    'address' => $fullAddress,
                    'priority' => 'Media',
                    'lat' => null,
                    'lng' => null,
                    'notes' => $notes,
                ]);
            }
        }

        return $stops;
    }

    /**
     * @return array{columns: array, sheets: array}
     */
    private function loadWorkbookPreview(string $path, ?string $extension = null, int $maxRows = 15): array
    {
        $extension = $extension ? strtolower($extension) : strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $columns = ['A', 'B', 'C', 'D', 'E'];
        $sheets = [];
        $canUseSpreadsheet = class_exists(IOFactory::class);

        if ($canUseSpreadsheet && in_array($extension, ['xlsx', 'xls'], true)) {
            $spreadsheet = IOFactory::load($path);
            foreach ($spreadsheet->getWorksheetIterator() as $worksheet) {
                $rows = $worksheet->toArray(null, true, true, true);
                $previewRows = [];
                $rowNumber = 0;
                foreach ($rows as $row) {
                    $rowNumber++;
                    if ($rowNumber > $maxRows) {
                        break;
                    }
                    $cells = [];
                    foreach ($columns as $col) {
                        $cells[$col] = isset($row[$col]) ? (string) $row[$col] : '';
                    }
                    $previewRows[] = [
                        'row' => $rowNumber,
                        'cells' => $cells,
                    ];
                }
                $sheets[] = [
                    'name' => $worksheet->getTitle(),
                    'rows' => $previewRows,
                ];
            }
        } else {
            $previewRows = [];
            if (($handle = fopen($path, 'r')) !== false) {
                $rowNumber = 0;
                while (($row = fgetcsv($handle, 0, ',')) !== false) {
                    $rowNumber++;
                    if ($rowNumber > $maxRows) {
                        break;
                    }
                    $cells = [];
                    foreach ($columns as $index => $col) {
                        $cells[$col] = isset($row[$index]) ? (string) $row[$index] : '';
                    }
                    $previewRows[] = [
                        'row' => $rowNumber,
                        'cells' => $cells,
                    ];
                }
                fclose($handle);
            }
            $sheets[] = [
                'name' => 'CSV',
                'rows' => $previewRows,
            ];
        }

        return [
            'columns' => $columns,
            'sheets' => $sheets,
        ];
    }

    private function plainPlans($plans)
    {
        return collect($plans)->map(function ($plan) {
            $stops = collect($plan['stops'] ?? [])->map(function ($stop) {
                return [
                    'code' => $stop['code'] ?? null,
                    'address' => $stop['address'] ?? null,
                    'lat' => $stop['lat'] ?? null,
                    'lng' => $stop['lng'] ?? null,
                    'priority' => $stop['priority'] ?? null,
                    'loose_stop_id' => $stop['loose_stop_id'] ?? null,
                ];
            })->values()->all();

            return [
                'carrier_id' => data_get($plan, 'carrier.id'),
                'stops' => $stops,
                'transporte_id' => $plan['transporte_id'] ?? null,
            ];
        })->values()->all();
    }

    private function persistLooseStops(Collection $stops, ?int $partyId, $user = null, ?UploadedFile $file = null): Collection
    {
        $source = $file ? $file->getClientOriginalName() : null;
        $userId = $user ? $user->id : null;

        return $stops->map(function ($stop) use ($partyId, $userId, $source) {
            $address = trim((string) ($stop['address'] ?? ''));
            $code = $stop['code'] ?? null;
            
            if ($address === '') {
                return $stop;
            }
            
            // Si ya viene con loose_stop_id (ej: desde mapa), respetarlo y no buscarlo de nuevo
            if (!empty($stop['loose_stop_id'])) {
                return $stop;
            }

            // Siempre buscar si existe un registro con estas características
            $existing = null;
            if ($partyId) {
                // Con partyId, usar fingerprint completo
                $fingerprint = TrafficLooseStop::fingerprint($partyId, $code, $address);
                $existing = TrafficLooseStop::pending()
                    ->where('fingerprint', $fingerprint)
                    ->first();
            } else {
                // Sin partyId, buscar por code+address sin importar party_id
                $existing = TrafficLooseStop::where('code', $code)
                    ->where('address', $address)
                    ->whereNull('assigned_route_id') // Solo los no asignados
                    ->first();
            }

            // Crear o actualizar registro
            $model = null;
            if ($partyId) {
                // Con partyId: crear/actualizar con fingerprint
                $fingerprint = TrafficLooseStop::fingerprint($partyId, $code, $address);
                $payload = [
                    'party_id' => $partyId,
                    'uploaded_by' => $userId,
                    'code' => $code,
                    'address' => $address,
                    'latitude' => isset($stop['lat']) ? (float) $stop['lat'] : (isset($stop['latitude']) ? (float) $stop['latitude'] : null),
                    'longitude' => isset($stop['lng']) ? (float) $stop['lng'] : (isset($stop['longitude']) ? (float) $stop['longitude'] : null),
                    'priority' => $stop['priority'] ?? null,
                    'notes' => $stop['notes'] ?? null,
                    'source_filename' => $source,
                    'fingerprint' => $fingerprint,
                ];

                if ($existing) {
                    $existing->fill($payload);
                    $existing->save();
                    $model = $existing;
                } else {
                    $model = TrafficLooseStop::create($payload);
                }
            } else {
                // Sin partyId: usar existente o crear sin party_id
                if ($existing) {
                    $model = $existing;
                } else {
                    // Crear sin party_id para rutas ad-hoc
                    $model = TrafficLooseStop::create([
                        'party_id' => null,
                        'uploaded_by' => $userId,
                        'code' => $code,
                        'address' => $address,
                        'latitude' => isset($stop['lat']) ? (float) $stop['lat'] : (isset($stop['latitude']) ? (float) $stop['latitude'] : null),
                        'longitude' => isset($stop['lng']) ? (float) $stop['lng'] : (isset($stop['longitude']) ? (float) $stop['longitude'] : null),
                        'priority' => $stop['priority'] ?? null,
                        'notes' => $stop['notes'] ?? null,
                        'source_filename' => $source,
                        'fingerprint' => null,
                    ]);
                }
            }

            // Agregar loose_stop_id al stop para uso posterior
            if ($model) {
                $stop['loose_stop_id'] = $model->id;
                if ($model->party_id) {
                    $stop['party_id'] = $model->party_id;
                }
            }

            return $stop;
        })->values();
    }

    /**
     * Agrega datos de peso/volumen a las paradas usando el TrafficLooseStop asociado.
     */
    private function enrichStopLoads(Collection $stops): Collection
    {
        $ids = $stops->pluck('loose_stop_id')->filter()->unique()->values();
        if ($ids->isEmpty()) {
            return $stops;
        }

        $looseStops = TrafficLooseStop::whereIn('id', $ids)->get([
            'id',
            'weight_actual',
            'weight_volumetric',
            'length',
            'width',
            'height',
        ])->keyBy('id');

        return $stops->map(function ($stop) use ($looseStops) {
            $model = $looseStops->get($stop['loose_stop_id'] ?? null);

            $weightActual = $model->weight_actual ?? ($stop['weight_actual'] ?? null);
            $weightVol = $model->weight_volumetric ?? ($stop['weight_volumetric'] ?? null);
            $length = $model->length ?? ($stop['length'] ?? null);
            $width = $model->width ?? ($stop['width'] ?? null);
            $height = $model->height ?? ($stop['height'] ?? null);

            $volumeFromDims = $this->volumeFromDims($length, $width, $height);
            $volume = $volumeFromDims ?? ($stop['volume_m3'] ?? null);

            $effectiveWeight = null;
            if ($weightActual !== null || $weightVol !== null) {
                $effectiveWeight = max(
                    $weightActual !== null ? (float) $weightActual : 0,
                    $weightVol !== null ? (float) $weightVol : 0
                );
            }

            $stop['weight_actual'] = $weightActual;
            $stop['weight_volumetric'] = $weightVol;
            $stop['effective_weight'] = $effectiveWeight;
            $stop['length'] = $length;
            $stop['width'] = $width;
            $stop['height'] = $height;
            $stop['volume_m3'] = $volume;

            return $stop;
        });
    }

    private function volumeFromDims($length, $width, $height): ?float
    {
        if (!is_numeric($length) || !is_numeric($width) || !is_numeric($height)) {
            return null;
        }

        $l = (float) $length;
        $w = (float) $width;
        $h = (float) $height;
        if ($l <= 0 || $w <= 0 || $h <= 0) {
            return null;
        }

        // Suponemos dimensiones en cm -> convertir a m3
        return ($l * $w * $h) / 1000000;
    }
}
