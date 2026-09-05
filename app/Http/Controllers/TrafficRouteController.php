<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\DeliveryReason;
use App\Models\Party;
use App\Models\TrafficLooseStop;
use App\Models\TrafficRoute;
use App\Models\TrafficRouteAssignment;
use App\Models\TrafficRouteStop;
use App\Models\TrafficRouteStopEvent;
use App\Models\TrafficRouteStopPhoto;
use App\Models\Transportista;
use App\Models\Transporte;
use App\Models\Location;
use App\Services\TrafficRouteService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;
use RuntimeException;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;


class TrafficRouteController extends Controller
{
    public function __construct(TrafficRouteService $routeService)
    {
        $this->routeService=$routeService;
    }

    public function index(Request $request)
    {
        $user = auth()->user();
        if ($user && $user->isTransportista()) {
            $transportistaProfile = $user->transportistaProfile;
            if ($transportistaProfile && ($transportistaProfile->portal_visibility ?? 'all') === 'settlements_only') {
                abort(403, 'No tienes permiso para ver las rutas.');
            }
        }
        $query = TrafficRoute::with(['transportista', 'transporte']);

        if ($user && $user->isTransportista()) {
            $transportistaId = $user->assignedTransportistaId();

            if ($transportistaId) {
                $query->where('transportista_id', $transportistaId);
                $query->whereNotNull('sent_at');
            } else {
                $query->whereRaw('0 = 1');
            }
        }

        $completion = $request->input('completion');
        if ( $user->isTransportista() && ! $request->filled('completion')) {
            $completion = 'pending';
        }

        if ($request->filled('transportista_id')) {
            $query->where('transportista_id', (int) $request->input('transportista_id'));
        }

        if ($completion === 'pending') {
            $query->whereNull('completed_at');
        } elseif ($completion === 'completed') {
            $query->whereNotNull('completed_at');
        }

        if ($request->filled('scheduled_date')) {
            $query->whereDate('scheduled_date', $request->input('scheduled_date'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('sent')) {
            if ($request->input('sent') === 'yes') {
                $query->whereNotNull('sent_at');
            } elseif ($request->input('sent') === 'no') {
                $query->whereNull('sent_at');
            }
        }

        $routes = $query->latest()->paginate(15)->withQueryString();
        $filters = $request->only(['transportista_id', 'scheduled_date', 'status', 'sent']);
        $filters['completion'] = $completion;
        $statusOptions = [
            'planned' => 'Planificada',
            'in_progress' => 'En progreso',
            'completed' => 'Completada',
            'cancelled' => 'Cancelada',
        ];

        return view('traffic.routes.index', [
            'routes' => $routes,
            'isTransportistaView' => $user && $user->isTransportista(),
            'transportistas' => Transportista::orderBy('name')->get(),
            'filters' => $filters,
            'statusOptions' => $statusOptions,
        ]);
    }

    public function editStops(TrafficRoute $route)
    {
    }

    public function create(Request $request)
    {
        $order = null;
        if ($request->filled('order')) {
            $order = Order::with('addresses')->find($request->query('order'));
        }

        return view('traffic.routes.create', [
            'transportistas' => Transportista::with(['transportes', 'user'])
                ->whereNotNull('user_id')
                ->orderBy('name')
                ->get(),
            'routeModel'     => null,
            'order'          => $order,
            'locations'      => Location::active()->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validatedRouteRequest($request);
        $scheduledDate = $request->filled('scheduled_date')
            ? Carbon::parse($request->input('scheduled_date'))
            : null;

        /** @var Transportista $carrier */
        $carrier = Transportista::findOrFail($data['transportista_id']);

        /** @var Transporte $vehicle */
        $vehicle = Transporte::where('id', $data['transporte_id'])
            ->where('transportista_id', $carrier->id)
            ->first();

        if (!$vehicle) {
            throw ValidationException::withMessages([
                'transporte_id' => 'El transporte seleccionado no pertenece al transportista.',
            ]);
        }

        $order = null;
        if (! empty($data['order_id'])) {
            $order = Order::with('addresses')->findOrFail($data['order_id']);
        }

        $stops = $order ? $this->stopsFromOrder($order) : array_values($data['stops']);
        if ($request->boolean('return_to_depot') && $request->filled('location_id')) {
            $stops = $this->appendDepotStop($stops, (int) $request->input('location_id'));
        }

        try {
            $route = $this->routeService->createRoute(
                $carrier,
                $vehicle,
                (int) $request->user()->id,
                $stops,
                $scheduledDate,
                $order,
                [
                    'avoid_tolls' => $request->boolean('avoid_tolls'),
                    'avoid_highways' => $request->boolean('avoid_highways'),
                ]
            );
        } catch (\Throwable $e) {
            $message = $e->getMessage();
            if ($e instanceof \Illuminate\Database\QueryException || str_contains($message, 'MySQL server has gone away')) {
                $message = 'No se pudo guardar la ruta. El mapa generado es muy pesado o la ruta tiene demasiadas paradas. Proba dividirla en rutas mas chicas y reintenta.';
            }
            return back()
                ->withInput()
                ->withErrors(['generator' => $message]);
        }

        if ($order && $order->status === 'pending') {
            $order->update(['status' => 'in_progress']);
        }

        if ($order) {
            $route->orders()->syncWithoutDetaching([$order->id]);
        }
        if ($scheduledDate) {
            $route->update(['scheduled_date' => $scheduledDate->toDateString()]);
        }

        return redirect()
            ->route('traffic.routes.show', $route)
            ->with('ok', 'Ruta generada correctamente.');
    }

    public function show(TrafficRoute $route)
    {
        $user = auth()->user();
        if ($user && $user->isTransportista() && $route->sent_at === null) {
            abort(403, 'Ruta no disponible.');
        }

        $route->load([
            'transportista',
            'transporte',
            'stops.deliveryReason',
            'stops.events.deliveryReason',
            'stops.events.user',
            'stops.photos.user',
            'order',
            'orders',
        ]);

        $assignments = $route->assignments()
            ->with(['transportista', 'transporte'])
            ->orderBy('assigned_at')
            ->get();

        $totalCompleted = $route->stops->whereNotNull('completed_at')->count();
        if ($assignments->isEmpty()) {
            $virtual = new TrafficRouteAssignment([
                'transportista_id' => $route->transportista_id,
                'transporte_id' => $route->transporte_id,
                'assigned_at' => $route->created_at,
                'completed_stops' => 0,
            ]);
            $virtual->setRelation('transportista', $route->transportista);
            $virtual->setRelation('transporte', $route->transporte);
            $assignments = collect([$virtual]);
        }

        $assignmentHistory = $assignments->values()->map(function ($assignment, $index) use ($assignments, $totalCompleted) {
            $currentCompleted = (int) ($assignment->completed_stops ?? 0);
            $nextCompleted = $assignments->get($index + 1)->completed_stops ?? $totalCompleted;
            $completedByCarrier = max(0, (int) $nextCompleted - $currentCompleted);

            return [
                'transportista' => $assignment->transportista,
                'transporte' => $assignment->transporte,
                'assigned_at' => $assignment->assigned_at,
                'completed_stops' => $completedByCarrier,
            ];
        });

        return view('traffic.routes.show', [
            'route' => $route,
            'deliveryReasons' => DeliveryReason::orderBy('order_index')->orderBy('name')->get(),
            'clients' => Party::orderBy('name')->get(),
            'transportistas' => Transportista::orderBy('name')->get(),
            'assignmentHistory' => $assignmentHistory,
        ]);
    }
    public function report(Request $request)
    {
        $user = auth()->user();
        $transportistaId = $user ? $user->assignedTransportistaId() : null;
        $isTransportista = $user && $user->isTransportista();

        $transportistas = Transportista::orderBy('name')->get();
        $orders = Order::orderByDesc('id')->limit(100)->get(); // limitar listado inicial
        $deliveryReasons = DeliveryReason::orderBy('order_index')->orderBy('name')->get();
        $routeStatusOptions = [
            'planned' => 'Planificada',
            'in_progress' => 'En progreso',
            'completed' => 'Completada',
            'cancelled' => 'Cancelada',
        ];

        $transportistaFilter = array_filter((array) $request->query('transportistas', []));
        $orderFilter = array_filter((array) $request->query('orders', []));
        $statusFilter = array_filter((array) $request->query('statuses', []));
        $routeStatusFilter = array_filter((array) $request->query('route_status', []));
        $reasonFilter = array_filter((array) $request->query('reasons', []));
        $sentFilter = $request->query('sent');
        $addressFilter = trim((string) $request->query('address', ''));

        $stopsQuery = TrafficRouteStop::with(['route.transportista', 'deliveryReason', 'order'])
            ->whereNotNull('latitude')
            ->whereNotNull('longitude');

        // Restricción por rol
        $stopsQuery->whereHas('route', function ($q) use ($isTransportista, $transportistaId, $transportistaFilter) {
            if ($isTransportista && $transportistaId) {
                $q->where('transportista_id', $transportistaId);
                $q->whereNotNull('sent_at');
            } elseif ($transportistaFilter) {
                $q->whereIn('transportista_id', $transportistaFilter);
            }
        });

        if ($sentFilter === 'yes') {
            $stopsQuery->whereHas('route', function ($q) {
                $q->whereNotNull('sent_at');
            });
        } elseif ($sentFilter === 'no') {
            $stopsQuery->whereHas('route', function ($q) {
                $q->whereNull('sent_at');
            });
        }

        if (! $isTransportista && empty($transportistaFilter)) {
            // Para no transportistas sin filtro, no acotar por transportista
        }

        if ($orderFilter) {
            $stopsQuery->where(function ($q) use ($orderFilter) {
                $q->whereIn('order_id', $orderFilter)
                    ->orWhereHas('route.orders', function ($sub) use ($orderFilter) {
                        $sub->whereIn('orders.id', $orderFilter);
                    });
            });
        }

        if ($statusFilter) {
            $stopsQuery->whereIn('status', $statusFilter);
        }

        if ($reasonFilter) {
            $stopsQuery->whereIn('delivery_reason_id', $reasonFilter);
        }

        if ($routeStatusFilter) {
            $stopsQuery->whereHas('route', function ($q) use ($routeStatusFilter) {
                $q->whereIn('status', $routeStatusFilter);
            });
        }

        if ($addressFilter !== '') {
            $stopsQuery->where(function ($q) use ($addressFilter) {
                $q->where('address', 'like', '%' . $addressFilter . '%')
                    ->orWhere('label', 'like', '%' . $addressFilter . '%');
            });
        }

        $stops = $stopsQuery->latest()->take(500)->get();
        $stopsForMap = $stops->map(function ($stop) {
            $carrierColor = optional(optional($stop->route)->transportista)->color;
            return [
                'route_id' => $stop->traffic_route_id ?? $stop->route_id,
                'lat' => $stop->latitude,
                'lng' => $stop->longitude,
                'status' => $stop->status,
                'is_extra' => (bool) $stop->is_extra,
                'sequence' => $stop->sequence,
                'label' => $stop->label,
                'route_code' => optional($stop->route)->code,
                'reason_color' => optional($stop->deliveryReason)->color,
                'reason_name' => optional($stop->deliveryReason)->name,
                'carrier_color' => $carrierColor,
            ];
        })->values();

        if ($request->query('export') === 'excel') {
            $headers = [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => 'attachment; filename="reporte-rutas.csv"',
            ];

            $callback = function () use ($stops) {
                $out = fopen('php://output', 'w');
                fputcsv($out, ['Ruta', 'Transportista', 'Pedido', 'Parada', 'Estado', 'Motivo']);
                foreach ($stops as $stop) {
                    $orderLabel = $stop->order
                        ? ($stop->order->order_number . ' - ' . $stop->order->client_name)
                        : '';
                    fputcsv($out, [
                        optional($stop->route)->code,
                        optional(optional($stop->route)->transportista)->name,
                        $orderLabel,
                        '#'.$stop->sequence.' '.$stop->label.' '.$stop->address,
                        TrafficRouteStop::statusOptions()[$stop->status] ?? 'Pendiente',
                        optional($stop->deliveryReason)->name ?? '',
                    ]);
                }
                fclose($out);
            };

            return response()->stream($callback, 200, $headers);
        }

        $stopsGrouped = $stops->groupBy(function ($stop) {
            return $stop->traffic_route_id ?? $stop->route_id;
        });
        $routeIds = $stopsGrouped->keys()->filter()->values();
        $routes = TrafficRoute::whereIn('id', $routeIds)
            ->with(['transportista', 'orders'])
            ->get()
            ->keyBy('id');
        $transportistasWithZones = Transportista::with(['zones', 'sharedZones'])
            ->whereIn('id', $routes->pluck('transportista_id')->unique())
            ->get();

        $routesData = $routeIds->map(function ($routeId) use ($routes, $stopsGrouped) {
            $route = $routes->get($routeId);
            $stopsForRoute = $stopsGrouped->get($routeId, collect());
            $statusCounts = [
                'delivered' => $stopsForRoute->where('status', TrafficRouteStop::STATUS_ENTREGADO)->count(),
                'failed' => $stopsForRoute->where('status', TrafficRouteStop::STATUS_NO_ENTREGADO)->count(),
                'pending' => $stopsForRoute->where('status', TrafficRouteStop::STATUS_PENDING)->count(),
            ];

            return [
                'route' => $route,
                'stops' => $stopsForRoute,
                'status_counts' => $statusCounts,
            ];
        })->values();

        $routesForMap = $routes->map(function ($route) {
            return [
                'code' => $route->code,
                'transportista' => optional($route->transportista)->name,
                'transportista_color' => optional($route->transportista)->color,
                'orders' => $route->orders->map(fn ($o) => $o->order_number . ' - ' . $o->client_name)->all(),
            ];
        });

        $zonesForMap = $transportistasWithZones->flatMap(function ($carrier) {
            $color = $carrier->color ?? '#2563eb';
            return $carrier->allZones()->map(function ($zone) use ($carrier, $color) {
                return [
                    'transportista_id' => $carrier->id,
                    'transportista' => $carrier->name,
                    'color' => $color,
                    'type' => $zone->type,
                    'center_lat' => $zone->center_lat,
                    'center_lng' => $zone->center_lng,
                    'radius_km' => $zone->radius_km,
                    'polygon' => $zone->polygon,
                    'priority' => $zone->priority,
                    'is_soft' => $zone->is_soft,
                ];
            });
        })->values();

        $sendableRouteIds = $routes->whereNull('sent_at')->pluck('id')->values()->all();
        $segmentsForMap = collect();

        $colorMap = [
            'low'      => '#2ecc71',
            'moderate' => '#f1c40f',
            'heavy'    => '#e74c3c',
            'severe'   => '#c0392b',
        ];

        foreach ($routes as $route) {
            $legs = collect(data_get($route->raw_payload, 'trip.legs', []));
            $carrierColor = $route->transportista->color ?? '#2563eb';

            foreach ($legs as $leg) {
                $congestionLevels = collect(data_get($leg, 'annotation.congestion', []))
                    ->map(fn ($value) => strtolower((string) $value))
                    ->filter()
                    ->values()
                    ->all();

                $defaultLevel = count($congestionLevels)
                    ? $congestionLevels[count($congestionLevels) - 1]
                    : 'unknown';
                $congestionIndex = 0;

                foreach (data_get($leg, 'steps', []) as $step) {
                    $coordinates = collect(data_get($step, 'geometry.coordinates', []))
                        ->filter(fn ($pair) => is_array($pair) && count($pair) === 2)
                        ->map(fn ($pair) => [$pair[1], $pair[0]])
                        ->values()
                        ->all();

                    if (count($coordinates) < 2) {
                        continue;
                    }

                    $pointCount = count($coordinates);
                    for ($i = 0; $i < $pointCount - 1; $i++) {
                        $level = $congestionLevels[$congestionIndex] ?? $defaultLevel;
                        $color = $level === 'unknown'
                            ? $carrierColor
                            : ($colorMap[$level] ?? $carrierColor);

                        $segmentsForMap->push([
                            'latlngs' => [$coordinates[$i], $coordinates[$i + 1]],
                            'color'   => $color,
                            'route_id' => $route->id,
                        ]);

                        $congestionIndex++;
                    }
                }
            }
        }

        $segmentsForMap = $segmentsForMap->filter(fn ($segment) => !empty($segment['latlngs']))->values();

        return view('traffic.routes.report', [
            'stops' => $stops,
            'routesData' => $routesData,
            'transportistas' => $transportistas,
            'orders' => $orders,
            'deliveryReasons' => $deliveryReasons,
            'routeStatusOptions' => $routeStatusOptions,
            'filters' => [
                'transportistas' => $transportistaFilter,
                'orders' => $orderFilter,
                'statuses' => $statusFilter,
                'route_status' => $routeStatusFilter,
                'reasons' => $reasonFilter,
                'sent' => $sentFilter,
                'address' => $addressFilter,
            ],
            'isTransportista' => $isTransportista,
            'stopsForMap' => $stopsForMap,
            'segmentsForMap' => $segmentsForMap,
            'routesForMap' => $routesForMap,
            'zonesForMap' => $zonesForMap,
            'sendableRouteIds' => $sendableRouteIds,
        ]);
    }

    public function edit(TrafficRoute $route)
    {
        $this->ensureRouteEditable($route);

        $route->load(['transportista', 'transporte', 'stops', 'order']);

        return view('traffic.routes.create', [
            'transportistas' => Transportista::with(['transportes', 'user'])
                ->whereNotNull('user_id')
                ->orderBy('name')
                ->get(),
            'routeModel'     => $route,
            'locations'      => Location::active()->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, TrafficRoute $route)
    {
        $this->ensureRouteEditable($route);

        $data = $this->validatedRouteRequest($request);
        $scheduledDate = $request->filled('scheduled_date')
            ? Carbon::parse($request->input('scheduled_date'))
            : null;

        $carrier = Transportista::findOrFail($data['transportista_id']);
        $vehicle = Transporte::where('id', $data['transporte_id'])
            ->where('transportista_id', $carrier->id)
            ->first();

        if (!$vehicle) {
            throw ValidationException::withMessages([
                'transporte_id' => 'El transporte seleccionado no pertenece al transportista.',
            ]);
        }

        $stops = array_values($data['stops']);
        if ($request->boolean('return_to_depot') && $request->filled('location_id')) {
            $stops = $this->appendDepotStop($stops, (int) $request->input('location_id'));
        }

        try {
            $route = $this->routeService->recalculateRoute(
                $route,
                $carrier,
                $vehicle,
                (int) $request->user()->id,
                $stops,
                $scheduledDate,
                $route->order,
                [
                    'avoid_tolls' => $request->boolean('avoid_tolls'),
                    'avoid_highways' => $request->boolean('avoid_highways'),
                ]
            );
        } catch (\Throwable $e) {
            $message = $e->getMessage();
            if ($e instanceof \Illuminate\Database\QueryException || str_contains($message, 'MySQL server has gone away')) {
                $message = 'No se pudo guardar la ruta. El mapa generado es muy pesado o la ruta tiene demasiadas paradas. Proba dividirla en rutas mas chicas y reintenta.';
            }
            return back()
                ->withInput()
                ->withErrors(['generator' => $message]);
        }
        if ($scheduledDate) {
            $route->update(['scheduled_date' => $scheduledDate->toDateString()]);
        }

        if ($route->order) {
            $route->orders()->syncWithoutDetaching([$route->order->id]);
        }

        return redirect()
            ->route('traffic.routes.show', $route)
            ->with('ok', 'Ruta actualizada correctamente.');
    }

    public function destroy(TrafficRoute $route)
    {
        $this->ensureRouteEditable($route);

        $this->releaseRouteLooseStops($route);
        $route->delete();

        return redirect()
            ->route('traffic.routes.index')
            ->with('ok', 'Ruta eliminada.');
    }

    public function destroyAll(Request $request)
    {
        $user = $request->user();
        abort_if($user && $user->isTransportista(), 403, 'No tenés permiso para eliminar rutas.');

        $routes = TrafficRoute::whereNull('started_at')->get();
        if ($routes->isEmpty()) {
            return redirect()
                ->route('traffic.routes.index')
                ->with('info', 'No hay rutas para eliminar.');
        }

        $deleted = 0;
        foreach ($routes as $route) {
            $this->releaseRouteLooseStops($route);
            $route->stops()->delete();
            $route->orders()->detach();
            $route->delete();
            $deleted++;
        }

        return redirect()
            ->route('traffic.routes.index')
            ->with('ok', "Se eliminaron {$deleted} rutas.");
    }

    public function export(Request $request, TrafficRoute $route)
    {
        $format = strtolower((string) $request->query('format', 'csv'));
        $route->load('stops');

        $statusOptions = TrafficRouteStop::statusOptions();
        $rows = $route->stops->map(function ($stop) use ($statusOptions) {
            return [
                'Secuencia' => $stop->sequence,
                'Etiqueta' => $stop->label,
                'Direccion' => $stop->address,
                'Ciudad' => $stop->city,
                'Codigo postal' => $stop->postal_code,
                'Contacto' => $stop->contact_name,
                'Telefono' => $stop->contact_phone,
                'Notas' => $stop->notes,
                'Latitud' => $stop->latitude,
                'Longitud' => $stop->longitude,
                'Estado' => $statusOptions[$stop->status] ?? $stop->status,
            ];
        })->values()->all();

        if ($format === 'xlsx') {
            if (!class_exists(Spreadsheet::class)) {
                return $this->exportCsv($rows, "ruta-{$route->code}.csv");
            }

            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $headers = array_keys($rows[0] ?? ['Secuencia' => null]);
            $sheet->fromArray($headers, null, 'A1');
            if (!empty($rows)) {
                $sheet->fromArray(array_map('array_values', $rows), null, 'A2');
            }

            $writer = new Xlsx($spreadsheet);
            $filename = "ruta-{$route->code}.xlsx";

            return Response::streamDownload(function () use ($writer) {
                $writer->save('php://output');
            }, $filename, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ]);
        }

        return $this->exportCsv($rows, "ruta-{$route->code}.csv");
    }

    private function exportCsv(array $rows, string $filename)
    {
        return Response::streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            if (!empty($rows)) {
                fputcsv($out, array_keys($rows[0]));
                foreach ($rows as $row) {
                    fputcsv($out, array_values($row));
                }
            }
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

    public function print(TrafficRoute $route)
    {
        $route->load(['stops', 'transportista', 'transporte']);

        $stops = $route->stops
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->sortBy('sequence')
            ->values();

        $mapImageUrl = null;
        $mapImageBase64 = null;

        if ($stops->count() >= 2) {
            // 1) Armamos la polyline con los puntos de la ruta
            // Mapbox usa "lon,lat"
            $coords = $stops
                ->map(fn($s) => $s->longitude . ',' . $s->latitude)
                ->implode(';');

            // Path: grosor 4px, color rojo f44
            $path = "path-4+f44($coords)";

            // 2) URL del static map de Mapbox
            $token = config('services.mapbox.token'); // o env('MAPBOX_TOKEN')
            $mapImageUrl = "https://api.mapbox.com/styles/v1/mapbox/streets-v12/static/{$path}/auto/800x400?access_token=" . urlencode($token);

            // 3) Opcional: traerla y embeber como base64 para evitar problemas con Dompdf
            try {
                $response = Http::get($mapImageUrl);
                if ($response->ok()) {
                    $mapImageBase64 = 'data:image/png;base64,' . base64_encode($response->body());
                }
            } catch (\Throwable $e) {
                // logueás el error si querés
            }
        }

        $pdf = Pdf::loadView('traffic.routes.pdf', [
            'route'           => $route,
            'mapImageUrl'     => $mapImageUrl,
            'mapImageBase64'  => $mapImageBase64,
        ]);

        return $pdf->stream("ruta-{$route->code}.pdf");
    }

    public function regenerate(TrafficRoute $route)
    {
        $this->ensureRouteEditable($route);

        // Cargamos relaciones necesarias
        $route->load(['transportista', 'transporte', 'stops']);

        // Validación de mínimo 2 paradas
        if ($route->stops->count() < 2) {
            return back()->withErrors([
                'generator' => 'Se necesitan al menos dos direcciones para recalcular la ruta.',
            ]);
        }

        // Armamos el payload de paradas
        $stopsPayload = $route->stops
            ->map(function ($stop) {
                return [
                    'label'         => $stop->label,
                    'address'       => $stop->address,
                    'contact_name'  => $stop->contact_name,
                    'contact_phone' => $stop->contact_phone,
                    'notes'         => $stop->notes,
                ];
            })
            ->values()
            ->all();

        try {
            $route = $this->routeService->recalculateRoute(
                $route,
                $route->transportista,
                $route->transporte,
                (int) auth()->id(),
                $stopsPayload,
                $route->scheduled_date // 👈 ojo acá si tu service espera un Carbon
            );
        } catch (RuntimeException $e) {
            return back()->withErrors(['generator' => $e->getMessage()]);
        }   
        //var_dump($route);
        //dd("");
        return redirect()
            ->route('traffic.routes.show', $route)
            ->with('ok', 'Ruta recalculada correctamente.');
    }


    public function duplicate(TrafficRoute $route)
    {
        $this->ensureRouteEditable($route);

        $route->load(['transportista', 'transporte', 'stops']);

        session()->flash('route_duplicate', [
            'transportista_id' => $route->transportista_id,
            'transporte_id'    => $route->transporte_id,
            'scheduled_date'   => optional($route->scheduled_date)->format('Y-m-d'),
            'stops'            => $route->stops
                ->map(function ($stop) {
                    return [
                        'label'         => $stop->label,
                        'address'       => $stop->address,
                        'contact_name'  => $stop->contact_name,
                        'contact_phone' => $stop->contact_phone,
                        'notes'         => $stop->notes,
                    ];
                })
                ->values()
                ->all(),
        ]);

        return redirect()
            ->route('traffic.routes.create')
            ->with('ok', 'Copiamos las direcciones para una nueva ruta. Podés ajustar y generar.');
    }

    public function start(TrafficRoute $route)
    {
        $this->authorizeTransportista($route);

        $updates = [];
        if (! $route->started_at) {
            $updates['started_at'] = now();
        }
        if ($route->status !== 'in_progress' && $route->status !== 'completed') {
            $updates['status'] = 'in_progress';
        }

        if (! empty($updates)) {
            $route->update($updates);
        }

        return back()->with('ok', 'Ruta iniciada. Registrá las paradas a medida que avance.');
    }

    public function send(TrafficRoute $route, Request $request)
    {
        $user = $request->user();
        abort_if($user && $user->isTransportista(), 403, 'No ten?s permiso para enviar rutas.');

        if ($route->sent_at === null) {
            $route->update([
                'sent_at' => now(),
                'sent_by' => optional($user)->id,
            ]);
        }

        return back()->with('ok', 'Ruta enviada al transportista.');
    }

    public function sendBatch(Request $request)
    {
        $user = $request->user();
        abort_if($user && $user->isTransportista(), 403, 'No ten?s permiso para enviar rutas.');

        $routeIds = array_values(array_filter(array_map('intval', (array) $request->input('route_ids', []))));
        if (!count($routeIds)) {
            return back()->with('info', 'No hay rutas para enviar.');
        }

        $updated = TrafficRoute::whereIn('id', $routeIds)
            ->whereNull('sent_at')
            ->update([
                'sent_at' => now(),
                'sent_by' => optional($user)->id,
            ]);

        return back()->with('ok', "Se enviaron {$updated} rutas.");
    }

    public function updateStop(Request $request, TrafficRoute $route, TrafficRouteStop $stop)
    {
        $this->authorizeTransportista($route);
        abort_if($stop->traffic_route_id !== $route->id, 404);

        $data = $request->validate([
            'status' => ['required', Rule::in([
                TrafficRouteStop::STATUS_ENTREGADO,
                TrafficRouteStop::STATUS_NO_ENTREGADO,
            ])],
            'recipient_is_owner' => ['nullable', 'boolean'],
            'recipient_dni' => ['nullable', 'string', 'max:60'],
            'recipient_name' => ['nullable', 'string', 'max:120'],
            'delivery_reason_id' => ['nullable', 'exists:delivery_reasons,id'],
            'status_notes' => ['nullable', 'string', 'max:500'],
        ]);

        $recipientIsOwner = array_key_exists('recipient_is_owner', $data)
            ? (bool) $data['recipient_is_owner']
            : true;

        if ($data['status'] === TrafficRouteStop::STATUS_ENTREGADO) {
            if (empty(trim((string) $data['recipient_dni'] ?? ''))) {
                return back()->withErrors(['recipient_dni' => 'Debés indicar el DNI del receptor.']);
            }
            if (! $recipientIsOwner && empty(trim((string) $data['recipient_name'] ?? ''))) {
                return back()->withErrors(['recipient_name' => 'Indicá nombre y apellido de quien recibe.']);
            }
        }

        if ($data['status'] === TrafficRouteStop::STATUS_NO_ENTREGADO
            && empty($data['delivery_reason_id'])) {
            return back()->withErrors(['delivery_reason_id' => 'Seleccioná el motivo de la no entrega.']);
        }

        $now = now();
        $stop->update([
            'status' => $data['status'],
            'delivery_reason_id' => $data['status'] === TrafficRouteStop::STATUS_NO_ENTREGADO
                ? $data['delivery_reason_id']
                : null,
            'recipient_dni' => $data['recipient_dni'],
            'recipient_name' => $recipientIsOwner ? null : $data['recipient_name'],
            'recipient_is_owner' => $recipientIsOwner,
            'status_notes' => $data['status_notes'],
            'attempted_at' => $now,
            'completed_at' => $now,
        ]);
        TrafficRouteStopEvent::create([
            'traffic_route_stop_id' => $stop->id,
            'user_id' => optional($request->user())->id,
            'status' => $data['status'],
            'delivery_reason_id' => $data['status'] === TrafficRouteStop::STATUS_NO_ENTREGADO
                ? $data['delivery_reason_id']
                : null,
            'recipient_name' => $recipientIsOwner ? null : $data['recipient_name'],
            'recipient_dni' => $data['recipient_dni'],
            'recipient_is_owner' => $recipientIsOwner,
            'status_notes' => $data['status_notes'],
            'happened_at' => $now,
        ]);

        if ($route->stops()->whereNull('completed_at')->count() === 0) {
            $routeUpdates = [];
            if (! $route->completed_at) {
                $routeUpdates['completed_at'] = $now;
            }
            if ($route->status !== 'completed') {
                $routeUpdates['status'] = 'completed';
            }
            if (! empty($routeUpdates)) {
                $route->update($routeUpdates);
            }
        }

        return back()->with('ok', 'Parada actualizada.');
    }


        public function uploadStopPhotos(Request $request, TrafficRoute $route, TrafficRouteStop $stop)
        {
    $user = $request->user();

    if ($user && $user->isTransportista()) {
        $this->authorizeTransportista($route);
    }

    abort_if($stop->traffic_route_id !== $route->id, 404);

    $data = $request->validate([
        'photos' => ['required', 'array', 'min:1'],
        'photos.*' => ['image', 'max:10240'],
    ]);

    // Guarda en: public_html/erp/storage/route-photos/{route}/stop-{stop}/
    $relativeDir = "storage/route-photos/{$route->id}/stop-{$stop->id}";
    $absoluteDir = public_path($relativeDir);

    if (!File::exists($absoluteDir)) {
        // si 0755 te falla en hosting, probá 0775
        File::makeDirectory($absoluteDir, 0775, true);
    }

    foreach ($data['photos'] as $file) {
        $filename = time() . '-' . Str::random(8) . '.' . $file->getClientOriginalExtension();

        // mueve físicamente al public
        $file->move($absoluteDir, $filename);

        // Este path es el que vas a usar para armar la URL /storage/...
        $path = "route-photos/{$route->id}/stop-{$stop->id}/{$filename}";

        TrafficRouteStopPhoto::create([
            'traffic_route_stop_id' => $stop->id,
            'user_id' => optional($user)->id,
            'path' => $path,
            'filename' => $file->getClientOriginalName(),
            'mimetype' => $file->getClientMimeType(),
            'size' => $file->getSize(),
        ]);
    }

    return back()->with('ok', 'Fotos guardadas.');
        }



    public function destroyStopPhoto(Request $request, TrafficRoute $route, TrafficRouteStop $stop, TrafficRouteStopPhoto $photo)
    {
        $user = $request->user();

        if ($user && $user->isTransportista()) {
            $this->authorizeTransportista($route);
        }

        abort_if($stop->traffic_route_id !== $route->id, 404);
        abort_if($photo->traffic_route_stop_id !== $stop->id, 404);

        $disk = Storage::disk('public');
        if ($disk->exists($photo->path)) {
            $disk->delete($photo->path);
        }

        $photo->delete();

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json(['ok' => true]);
        }

        return back()->with('ok', 'Foto eliminada.');
    }


    public function addAdhocStop(Request $request, TrafficRoute $route)
    {
        $this->authorizeAdhocStop($route);

        $data = $request->validate([
            'party_id' => ['required', 'exists:parties,id'],
            'address' => ['required', 'string', 'max:255'],
            'label' => ['nullable', 'string', 'max:120'],
            'contact_name' => ['nullable', 'string', 'max:120'],
            'contact_phone' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:500'],
            'latitude' => ['nullable', 'numeric'],
            'longitude' => ['nullable', 'numeric'],
        ]);

        $client = Party::findOrFail($data['party_id']);
        $clientName = $client->business_name ?: $client->name;

        $order = Order::create([
            'order_number' => $this->generateAdhocOrderNumber(),
            'party_id' => $client->id,
            'client_name' => $clientName,
            'client_contact' => $data['contact_name'],
            'client_phone' => $data['contact_phone'],
            'order_date' => now(),
            'status' => 'in_progress',
            'delivery_instructions' => $data['notes'] ?? null,
        ]);

        $order->addresses()->create([
            'sequence' => 1,
            'label' => $data['label'] ?? 'Parada extra',
            'address' => $data['address'],
            'contact_name' => $data['contact_name'],
            'contact_phone' => $data['contact_phone'],
            'notes' => $data['notes'],
            'latitude' => $data['latitude'] ?? null,
            'longitude' => $data['longitude'] ?? null,
        ]);

        $route->orders()->syncWithoutDetaching([$order->id]);

        $nextSequence = (int) $route->stops()->max('sequence') + 1;

        $route->stops()->create([
            'order_id' => $order->id,
            'sequence' => $nextSequence,
            'is_extra' => true,
            'label' => $data['label'] ?? 'Parada extra',
            'address' => $data['address'],
            'contact_name' => $data['contact_name'],
            'contact_phone' => $data['contact_phone'],
            'notes' => $data['notes'],
            'status' => TrafficRouteStop::STATUS_PENDING,
            'latitude' => $data['latitude'] ?? null,
            'longitude' => $data['longitude'] ?? null,
        ]);

        return back()->with('ok', 'Parada extra agregada a la ruta.');
    }

    public function reassign(Request $request, TrafficRoute $route)
    {
        $user = $request->user();
        abort_if($user && $user->isTransportista(), 403, 'No tenés permiso para reasignar rutas.');

        $data = $request->validate([
            'transportista_id' => ['required', 'exists:transportistas,id'],
            'transporte_id' => ['required', 'exists:transportes,id'],
        ]);

        $carrier = Transportista::findOrFail($data['transportista_id']);
        $vehicle = Transporte::where('id', $data['transporte_id'])
            ->where('transportista_id', $carrier->id)
            ->first();

        if (! $vehicle) {
            throw ValidationException::withMessages([
                'transporte_id' => 'El transporte seleccionado no pertenece al transportista.',
            ]);
        }

        $completedStops = $route->stops()->whereNotNull('completed_at')->count();

        if (! $route->assignments()->exists()) {
            $route->assignments()->create([
                'transportista_id' => $route->transportista_id,
                'transporte_id' => $route->transporte_id,
                'assigned_by' => optional($user)->id,
                'assigned_at' => $route->created_at ?? now(),
                'completed_stops' => 0,
            ]);
        }

        $route->assignments()->create([
            'transportista_id' => $carrier->id,
            'transporte_id' => $vehicle->id,
            'assigned_by' => optional($user)->id,
            'assigned_at' => now(),
            'completed_stops' => $completedStops,
        ]);

        $route->update([
            'transportista_id' => $carrier->id,
            'transporte_id' => $vehicle->id,
        ]);

        return back()->with('ok', 'Transportista reasignado.');
    }

private function authorizeTransportista(TrafficRoute $route): void
    {
        $user = auth()->user();
        $transportistaId = $user ? $user->assignedTransportistaId() : null;

        abort_unless(
            $user && $user->isTransportista() && $transportistaId && $transportistaId === $route->transportista_id,
            403,
            'No tenés permiso para acceder a esta ruta.'
        );
    }

    private function ensureRouteEditable(TrafficRoute $route): void
    {
        $user = auth()->user();
        if ($route->started_at) {
            abort(403, 'No se puede editar o eliminar una ruta que ya fue iniciada.');
        }

        abort_if($user && $user->isTransportista(), 403, 'No tenés permiso para modificar esta ruta.');
    }

    private function releaseRouteLooseStops(TrafficRoute $route): void
    {
        TrafficLooseStop::releaseFromRoute($route->id);
    }

    private function stopsFromOrder(Order $order): array
    {
        return $order->addresses
            ->map(function ($address) {
                return [
                    'label' => $address->label,
                    'address' => $address->address,
                    'contact_name' => $address->contact_name,
                    'contact_phone' => $address->contact_phone,
                    'notes' => $address->notes,
                    'city' => $address->city,
                    'postal_code' => $address->postal_code,
                    'latitude' => $address->latitude,
                    'longitude' => $address->longitude,
                ];
            })
            ->values()
            ->all();
    }


    private function validatedRouteRequest(Request $request): array
    {
        return $request->validate([
            'transportista_id'        => ['required', 'exists:transportistas,id'],
            'transporte_id'           => ['required', 'exists:transportes,id'],
            'scheduled_date'          => ['nullable', 'date'],
            'order_id'                => ['nullable', 'exists:orders,id'],
            'stops'                   => ['required_without:order_id', 'array', 'min:2'],
            'stops.*.address'         => ['required_without:order_id', 'string', 'max:255'],
            'stops.*.label'           => ['nullable', 'string', 'max:120'],
            'stops.*.contact_name'    => ['nullable', 'string', 'max:120'],
            'stops.*.contact_phone'   => ['nullable', 'string', 'max:50'],
            'stops.*.notes'           => ['nullable', 'string', 'max:500'],
            'return_to_depot'         => ['nullable', 'boolean'],
            'location_id'             => ['nullable', 'exists:locations,id', 'required_if:return_to_depot,1'],
            'avoid_tolls'             => ['nullable', 'boolean'],
            'avoid_highways'          => ['nullable', 'boolean'],
        ]);
    }

    private function authorizeAdhocStop(TrafficRoute $route): void
    {
        $user = auth()->user();
        abort_unless($user && ! $user->isTransportista(), 403, 'No tenés permiso para agregar paradas en esta ruta.');
    }

    private function generateAdhocOrderNumber(): string
    {
        return 'ADHOC-' . now()->format('Ymd-His') . '-' . Str::upper(Str::random(4));
    }

    private function appendDepotStop(array $stops, int $locationId): array
    {
        $location = Location::active()->find($locationId);
        if (! $location) {
            throw ValidationException::withMessages(['location_id' => 'Depósito no encontrado o inactivo.']);
        }

        $stops[] = [
            'label' => $location->name,
            'address' => $location->address,
            'latitude' => $location->latitude,
            'longitude' => $location->longitude,
            'notes' => 'Regreso a depósito',
        ];

        return $stops;
    }
}
