<?php

namespace App\Services;

use App\Models\Order;
use App\Models\TrafficRoute;
use App\Models\Transportista;
use App\Models\Transporte;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Models\TrafficLooseStop;

use RuntimeException;

class TrafficRouteService
{
    public function createRoute(
        Transportista $carrier,
        Transporte $vehicle,
        int $userId,
        array $rawStops,
        ?Carbon $scheduledDate = null,
        ?Order $order = null,
        array $options = []
    ): TrafficRoute {
        $stops = $this->geocodeStops($rawStops);
        $payload = $this->buildRouteData($carrier, $vehicle, $userId, $stops, $scheduledDate, $order, $options);

        return DB::transaction(function () use ($payload) {
            $attributes = $payload['attributes'];
            $attributes['code'] = TrafficRoute::generateCode();
            $attributes['status'] = 'planned';

            $route = TrafficRoute::create($attributes);
            $this->syncStops($route, $payload['stops']);

            return $route->fresh(['transportista', 'transporte', 'stops']);
        });
    }

    public function recalculateRoute(
        TrafficRoute $route,
        Transportista $carrier,
        Transporte $vehicle,
        int $userId,
        array $rawStops,
        ?Carbon $scheduledDate = null,
        ?Order $order = null,
        array $options = []
    ): TrafficRoute {
        $stops = $this->geocodeStops($rawStops);
        $payload = $this->buildRouteData($carrier, $vehicle, $userId, $stops, $scheduledDate, $order, $options);

        return DB::transaction(function () use ($route, $payload) {
            $route->update($payload['attributes']);
            $route->stops()->delete();
            $this->syncStops($route, $payload['stops']);

            return $route->fresh(['transportista', 'transporte', 'stops']);
        });
    }

    public function createRouteFromSolver(
        Transportista $carrier,
        Transporte $vehicle,
        int $userId,
        array $orderedStops,
        ?Carbon $scheduledDate = null,
        array $options = []
    ): TrafficRoute {
        if (count($orderedStops) < 2) {
            throw new RuntimeException('Se necesitan al menos dos direcciones para generar la ruta.');
        }

        $stops = $this->normalizeSolverStops($orderedStops);
        $payload = $this->requestDirectionsForOrdered($stops, $options);
        $summary = $this->summarizeTraffic($payload['trip'] ?? []);

        $this->logPayloadSize($payload);
        $rawPayload = $this->shrinkPayload($payload);

        return DB::transaction(function () use ($carrier, $vehicle, $userId, $scheduledDate, $stops, $payload, $rawPayload, $summary, $options) {
            $route = TrafficRoute::create([
                'code' => TrafficRoute::generateCode(),
                'status' => 'planned',
                'transportista_id' => $carrier->id,
                'transporte_id' => $vehicle->id,
                'user_id' => $userId,
                'scheduled_date' => $scheduledDate ? $scheduledDate->toDateString() : null,
                'distance_meters' => data_get($payload, 'trip.distance'),
                'duration_seconds' => data_get($payload, 'trip.duration'),
                'traffic_summary' => $summary['summary'] ?? null,
                'congestion_level' => $summary['level'] ?? null,
                'geometry' => data_get($payload, 'trip.geometry'),
                'traffic_report' => $summary['report'] ?? null,
                'raw_payload' => $rawPayload,
            ]);

            $createdStops = $this->syncStops($route, $stops);

            $this->markLooseStopsAsAssigned($route, $stops, $createdStops);

            return $route->fresh(['transportista', 'transporte', 'stops']);

        });
    }

    private function normalizeSolverStops(array $orderedStops): array
    {
        $normalized = [];
        foreach (array_values($orderedStops) as $index => $stop) {
            if (!isset($stop['latitude'], $stop['longitude'])) {
                continue;
            }

            $normalized[] = [
                'sequence' => $index + 1,
                'label' => $stop['label'] ?? null,
                'address' => $stop['address'] ?? null,
                'latitude' => round((float) $stop['latitude'], 7),
                'longitude' => round((float) $stop['longitude'], 7),
                'city' => $stop['city'] ?? null,
                'postal_code' => $stop['postal_code'] ?? null,
                'contact_name' => $stop['contact_name'] ?? null,
                'contact_phone' => $stop['contact_phone'] ?? null,
                'notes' => $stop['notes'] ?? null,
                'traffic_loose_stop_id' => $stop['traffic_loose_stop_id'] ?? $stop['loose_stop_id'] ?? null,
            ];
        }

        \Log::info('TrafficRouteService: normalizeSolverStops', [
            'input_count' => count($orderedStops),
            'output_count' => count($normalized),
            'first_input_loose_id' => count($orderedStops) > 0 ? ($orderedStops[0]['traffic_loose_stop_id'] ?? null) : null,
            'first_output_loose_id' => count($normalized) > 0 ? ($normalized[0]['traffic_loose_stop_id'] ?? null) : null,
        ]);

        if (count($normalized) < 2) {
            throw new RuntimeException('Se necesitan al menos dos direcciones con coordenadas.');
        }

        return $normalized;
    }

    private function distanceMeters(array $stops): ?int
    {
        if (count($stops) < 2) {
            return null;
        }

        $km = 0;
        for ($i = 0; $i < count($stops) - 1; $i++) {
            $a = $stops[$i];
            $b = $stops[$i + 1];
            $km += $this->haversine($a['latitude'], $a['longitude'], $b['latitude'], $b['longitude']);
        }

        return (int) round($km * 1000);
    }

    private function haversine(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371; // km
        $latFrom = deg2rad($lat1);
        $lonFrom = deg2rad($lon1);
        $latTo = deg2rad($lat2);
        $lonTo = deg2rad($lon2);

        $latDelta = $latTo - $latFrom;
        $lonDelta = $lonTo - $lonFrom;

        $angle = 2 * asin(sqrt(pow(sin($latDelta / 2), 2) +
            cos($latFrom) * cos($latTo) * pow(sin($lonDelta / 2), 2)));
        return $earthRadius * $angle;
    }

    /**
     * Fallback simple: arranca en el primer punto y va siempre al mas cercano.
     * Permite reordenar aunque Mapbox no optimice (por ejemplo, sin token o sin cuota).
     */
    private function fallbackReorderByDistance(array $stops): array
    {
        if (count($stops) <= 2) {
            return $stops;
        }

        $ordered = [];
        $remaining = $stops;
        $current = array_shift($remaining); // mantenemos el inicio que ingreso el usuario
        $ordered[] = $current;

        while (!empty($remaining)) {
            $nextIndex = null;
            $bestDistance = PHP_FLOAT_MAX;
            foreach ($remaining as $idx => $candidate) {
                if (!isset($candidate['latitude'], $candidate['longitude'])) {
                    continue;
                }
                $dist = $this->haversine(
                    (float) $current['latitude'],
                    (float) $current['longitude'],
                    (float) $candidate['latitude'],
                    (float) $candidate['longitude']
                );
                if ($dist < $bestDistance) {
                    $bestDistance = $dist;
                    $nextIndex = $idx;
                }
            }

            if ($nextIndex === null) {
                // sin coordenadas validas, mantenemos el orden restante
                foreach ($remaining as $rest) {
                    $ordered[] = $rest;
                }
                break;
            }

            $current = $remaining[$nextIndex];
            $ordered[] = $current;
            unset($remaining[$nextIndex]);
        }

        return array_values($ordered);
    }


    private function buildRouteData(
        Transportista $carrier,
        Transporte $vehicle,
        int $userId,
        array $stops,
        ?Carbon $scheduledDate = null,
        ?Order $order = null,
        array $options = []
    ): array {
        $payload = $this->requestOptimizedTrip($stops, $options);
        $warning = $payload['optimization_warning'] ?? null;
        if ($warning) {
            // Recalcular la ruta con un orden local si Mapbox no optimiza.
            $reordered = $this->fallbackReorderByDistance($stops);
            $payload = $this->requestDirectionsForOrdered($reordered, $options);
            $payload['optimization_warning'] = $warning;
        }

        $this->logPayloadSize($payload);
        $payload['options'] = [
            'avoid_tolls' => (bool) ($options['avoid_tolls'] ?? false),
            'avoid_highways' => (bool) ($options['avoid_highways'] ?? false),
        ];
        $orderedFromPayload = $payload['ordered_stops'] ?? null;
        if (is_array($orderedFromPayload) && count($orderedFromPayload) === count($stops)) {
            $reordered = $orderedFromPayload;
        } else {
            $reordered = $this->reorderStops($stops, $payload['trip'] ?? [], $payload['waypoints'] ?? []);
        }
        $summary = $this->summarizeTraffic($payload['trip'] ?? []);

        $scheduledDate = $scheduledDate ?: Carbon::today();
        $attributes = [
            'transportista_id' => $carrier->id,
            'transporte_id' => $vehicle->id,
            'user_id' => $userId,
            'scheduled_date' => $scheduledDate->toDateString(),
            'distance_meters' => isset($payload['trip']['distance']) ? (int) round($payload['trip']['distance']) : null,
            'duration_seconds' => isset($payload['trip']['duration']) ? (int) round($payload['trip']['duration']) : null,
            'traffic_summary' => $summary['summary'] ?? null,
            'congestion_level' => $summary['level'] ?? null,
            'geometry' => $payload['trip']['geometry'] ?? null,
            'traffic_report' => $summary['report'] ?? null,
            'raw_payload' => $this->shrinkPayload($payload),
            'order_id' => $order ? $order->id : null,
        ];

        $stopPayloads = [];
        foreach ($reordered as $index => $stop) {
            $stopPayloads[] = [
                'sequence' => $index + 1,
                'label' => $stop['label'] ?? null,
                'address' => $stop['address'] ?? null,
                'latitude' => isset($stop['latitude']) ? round((float) $stop['latitude'], 7) : null,
                'longitude' => isset($stop['longitude']) ? round((float) $stop['longitude'], 7) : null,
                'city' => $stop['city'] ?? null,
                'postal_code' => $stop['postal_code'] ?? null,
                'contact_name' => $stop['contact_name'] ?? null,
                'contact_phone' => $stop['contact_phone'] ?? null,
                'notes' => $stop['notes'] ?? null,
            ];
        }

        return [
            'attributes' => $attributes,
            'stops' => $stopPayloads,
        ];
    }

    private function syncStops(TrafficRoute $route, array $stops): \Illuminate\Support\Collection
    {
        $created = collect();

        \Log::info('TrafficRouteService: syncStops', [
            'route_id' => $route->id,
            'stops_count' => count($stops),
            'first_stop_keys' => count($stops) > 0 ? array_keys($stops[0]) : [],
            'first_stop_loose_id' => count($stops) > 0 ? ($stops[0]['traffic_loose_stop_id'] ?? null) : null,
        ]);

        foreach ($stops as $stop) {
            $created->push($route->stops()->create($stop));
        }

        return $created;
    }


    private function geocodeStops(array $stops): array
    {
        if (count($stops) < 2) {
            throw new RuntimeException('Se necesitan al menos dos direcciones para generar la ruta.');
        }

        $token    = $this->mapboxToken();
        $geocoded = [];

        foreach ($stops as $index => $stop) {
            $address = trim((string) ($stop['address'] ?? ''));
            $hasCoords = isset($stop['latitude'], $stop['longitude']) && is_numeric($stop['latitude']) && is_numeric($stop['longitude']);

            if ($hasCoords) {
                $geocoded[] = [
                    'address'       => $address ?: ($stop['label'] ?? 'Punto'),
                    'latitude'      => (float) $stop['latitude'],
                    'longitude'     => (float) $stop['longitude'],
                    'city'          => $stop['city'] ?? null,
                    'postal_code'   => $stop['postal_code'] ?? null,
                    'label'         => $stop['label'] ?? null,
                    'contact_name'  => $stop['contact_name'] ?? null,
                    'contact_phone' => $stop['contact_phone'] ?? null,
                    'notes'         => $stop['notes'] ?? null,
                ];
                continue;
            }

            if ($address === '') {
                throw new RuntimeException('La dirección #' . ($index + 1) . ' es obligatoria.');
            }

            $response = Http::timeout(20)->get(sprintf(
                'https://api.mapbox.com/geocoding/v5/mapbox.places/%s.json',
                urlencode($address)
            ), [
                'language'     => 'es',
                'country'      => 'AR', // <-- limitamos a Argentina para evitar matches raros
                'access_token' => $token,
                'limit'        => 1,
            ]);

            if (!$response->successful()) {
                throw new RuntimeException('No se pudo geolocalizar la dirección: ' . $address);
            }

            $feature = $response->json('features.0');
            if (!$feature || empty($feature['center'])) {
                throw new RuntimeException('Direccion sin resultados: ' . $address);
            }

            // Mapbox center = [lon, lat]
            $coordinates = $feature['center'];
            $context     = collect($feature['context'] ?? []);

            $geocoded[] = [
                'address'       => $feature['place_name'] ?? $address,
                'latitude'      => (float) $coordinates[1],
                'longitude'     => (float) $coordinates[0],
                'city'          => $this->extractFromContext($context, 'place'),
                'postal_code'   => $this->extractFromContext($context, 'postcode'),
                'label'         => $stop['label'] ?? null,
                'contact_name'  => $stop['contact_name'] ?? null,
                'contact_phone' => $stop['contact_phone'] ?? null,
                'notes'         => $stop['notes'] ?? null,
            ];
        }

        return $geocoded;
    }

    /**
     * Directions para una lista ya ordenada de paradas (no reordena).
     * Permite mas de 25 puntos dividiendo en bloques respetando el orden.
     */
    private function requestDirectionsForOrdered(array $orderedStops, array $options = []): array
    {
        if (count($orderedStops) < 2) {
            throw new \RuntimeException('Se necesitan al menos dos puntos para calcular la ruta.');
        }

        $token = $this->mapboxToken();
        $exclude = collect([
            'toll' => !empty($options['avoid_tolls']),
            'motorway' => !empty($options['avoid_highways']),
        ])->filter()->keys()->implode(',');

        $maxPerRequest = 25; // limite de Mapbox Directions
        $totalDistance = 0;
        $totalDuration = 0;
        $allLegs = [];
        $allWaypoints = [];
        $orderedIndices = range(0, count($orderedStops) - 1);
        $mergedCoordinates = [];
        $hasLineString = false;

        $currentStart = 0;
        $prevLastStop = null;

        while ($currentStart < count($orderedStops)) {
            // Tomamos hasta 25 puntos, compartiendo el ultimo punto con el siguiente bloque para mantener continuidad.
            if ($prevLastStop) {
                $slice = array_slice($orderedStops, $currentStart, $maxPerRequest - 1);
                array_unshift($slice, $prevLastStop);
                $currentStart += ($maxPerRequest - 1);
            } else {
                $slice = array_slice($orderedStops, $currentStart, $maxPerRequest);
                $currentStart += $maxPerRequest;
            }

            if (count($slice) < 2) {
                break;
            }

            $coordsOrdered = implode(';', array_map(function ($stop) {
                return $stop['longitude'] . ',' . $stop['latitude'];
            }, $slice));

            $params = [
                'geometries'   => 'geojson',
                'overview'     => 'full',
                'steps'        => 'true',
                'annotations'  => 'duration,distance,congestion',
                'access_token' => $token,
            ];
            if (!empty($exclude)) {
                $params['exclude'] = $exclude;
            }

            $dirResponse = Http::timeout(30)->get(
                'https://api.mapbox.com/directions/v5/mapbox/driving-traffic/' . urlencode($coordsOrdered),
                $params
            );

            if (!$dirResponse->successful() || empty($dirResponse->json('routes'))) {
                throw new \RuntimeException('Mapbox no devolvio rutas para el orden indicado.');
            }

            $route = $dirResponse->json('routes.0') ?? [];
            $legs = $route['legs'] ?? [];

            // Evitar duplicar el primer leg/waypoint del bloque (punto compartido)
            if ($prevLastStop) {
                $legs = array_slice($legs, 1);
                $waypointsChunk = array_slice($dirResponse->json('waypoints') ?? [], 1);
            } else {
                $waypointsChunk = $dirResponse->json('waypoints') ?? [];
            }

            $totalDistance += (float) ($route['distance'] ?? 0);
            $totalDuration += (float) ($route['duration'] ?? 0);
            $allLegs = array_merge($allLegs, $legs);
            $allWaypoints = array_merge($allWaypoints, $waypointsChunk);

            $geometry = $route['geometry'] ?? null;
            if (is_array($geometry) && ($geometry['type'] ?? null) === 'LineString' && isset($geometry['coordinates'])) {
                if ($hasLineString && !empty($mergedCoordinates)) {
                    $mergedCoordinates = array_merge($mergedCoordinates, array_slice($geometry['coordinates'], 1));
                } else {
                    $mergedCoordinates = $geometry['coordinates'];
                    $hasLineString = true;
                }
            } elseif (!$hasLineString && $geometry) {
                // fallback: guardar la primera geometria aunque no sea LineString clasico
                $mergedCoordinates = $geometry;
                $hasLineString = true;
            }

            $prevLastStop = end($slice);
        }

        $finalGeometry = null;
        if ($hasLineString && is_array($mergedCoordinates) && isset($mergedCoordinates[0])) {
            $finalGeometry = [
                'type' => 'LineString',
                'coordinates' => $mergedCoordinates,
            ];
        } elseif ($hasLineString) {
            $finalGeometry = $mergedCoordinates;
        }

        return [
            'trip' => [
                'distance' => $totalDistance > 0 ? $totalDistance : null,
                'duration' => $totalDuration > 0 ? $totalDuration : null,
                'geometry' => $finalGeometry,
                'legs' => $allLegs,
                'ordered_indices' => $orderedIndices,
            ],
            'waypoints' => $allWaypoints,
            'ordered_stops' => $orderedStops,
            'options' => [
                'avoid_tolls' => (bool) ($options['avoid_tolls'] ?? false),
                'avoid_highways' => (bool) ($options['avoid_highways'] ?? false),
            ],
        ];
    }


    private function requestOptimizedTrip_ohne_verkehr(array $stops): array
    {
        $token = $this->mapboxToken();

        // 1) Construir coordenadas en formato lon,lat
        $coords = collect($stops)
            ->map(function ($stop) {
                $lat = (float) $stop['latitude'];
                $lon = (float) $stop['longitude'];
                if (abs($lat) > 90 || abs($lon) > 180) {
                    throw new RuntimeException("Coordenadas fuera de rango: lat=$lat lon=$lon");
                }
                return $lon . ',' . $lat;
            })
            ->implode(';');

        $coords = preg_replace('/\s+/', '', trim($coords));

        // Paso A: Optimization → driving (no driving-traffic)
        $optParams = [
            'source'       => 'any',
            'destination'  => 'any',
            'roundtrip'    => 'false',
            'overview'     => 'false',
            'access_token' => $token,
        ];
        if (!empty($exclude)) {
            $optParams['exclude'] = $exclude;
        }

        $optResponse = Http::timeout(30)->get(
            'https://api.mapbox.com/optimized-trips/v1/mapbox/driving/' . urlencode($coords),
            $optParams
        );
        //dd($optResponse->json());   
        if (!$optResponse->successful() || strtoupper((string) $optResponse->json('code')) !== 'OK') {
            // Fallback: usar el orden dado sin optimizar
            return $this->requestDirectionsForOrdered($stops, [
                'avoid_tolls' => !empty($options['avoid_tolls']),
                'avoid_highways' => !empty($options['avoid_highways']),
            ]);
        }

        $trip = $optResponse->json('trips.0');
        if (!$trip) {
            return $this->requestDirectionsForOrdered($stops, [
                'avoid_tolls' => !empty($options['avoid_tolls']),
                'avoid_highways' => !empty($options['avoid_highways']),
            ]);
        }

        $waypointOrder = $trip['waypoint_order'] ?? [];

        // Orden final: primer punto + orden de los del medio + último punto
        $waypoints = $optResponse->json('waypoints') ?? [];

        // 1) Intentar con 'waypoint_index' de cada waypoint (es la forma más clara)
        $orderedIndices = collect($waypoints)
            ->map(function ($wp, $origIndex) {
                return [
                    'orig' => $origIndex,                               // índice original que enviaste
                    'pos'  => $wp['waypoint_index'] ?? PHP_INT_MAX,     // posición en el viaje optimizado
                ];
            })
            ->sortBy('pos')   // ordená por la posición optimizada
            ->pluck('orig')   // devolvé el índice original en ese orden
            ->values()
            ->all();

        // 2) Fallback si faltaran waypoint_index (raro, pero posible)
        if (in_array(PHP_INT_MAX, array_map(function($i){return is_array($i)?($i['pos']??null):null;}, $orderedIndices), true)
            || count($orderedIndices) !== count($stops)) {

            $waypointOrder = $trip['waypoint_order'] ?? [];
            // waypoint_order trae SOLO los del medio cuando usás source=first y destination=last
            $orderedIndices = [0];
            foreach ($waypointOrder as $i) { $orderedIndices[] = $i; }
            $orderedIndices[] = count($stops) - 1;
        }

        // 3) Reordenar stops y coords según 'orderedIndices'
        $orderedStops = [];
        foreach ($orderedIndices as $idx) {
            if (!isset($stops[$idx])) continue;
            $orderedStops[] = $stops[$idx];
        }

        $coordsOrdered = implode(';', array_map(function ($stop) {
            return $stop['longitude'] . ',' . $stop['latitude'];
        }, $orderedStops));


        // Paso B: Directions con tráfico (driving-traffic)
        $dirResponse = Http::timeout(30)->get(
            'https://api.mapbox.com/directions/v5/mapbox/driving-traffic/' . urlencode($coordsOrdered),
            [
                'geometries'   => 'geojson',
                'overview'     => 'full',
                'steps'        => 'true',
                'annotations'  => 'duration,distance,congestion',
                'access_token' => $token,
            ]
        );

        if (!$dirResponse->successful() || empty($dirResponse->json('routes'))) {
            throw new RuntimeException('Mapbox no devolvió rutas para el orden calculado.');
        }

        $route = $dirResponse->json('routes.0');

        return [
            'trip' => [
                'distance'        => $route['distance'] ?? null,
                'duration'        => $route['duration'] ?? null,
                'geometry'        => $route['geometry'] ?? null,
                'legs'            => $route['legs'] ?? [],
                'waypoint_order'  => $waypointOrder,
                'ordered_indices' => $orderedIndices, // ← cambio clave aquí
            ],
            'waypoints'     => $dirResponse->json('waypoints') ?? [],
            'ordered_stops' => $orderedStops,
        ];

    }

    private function requestOptimizedTrip(array $stops, array $options = []): array

    {

        $token = $this->mapboxToken();

        $exclude = collect([

            'toll' => !empty($options['avoid_tolls']),

            'motorway' => !empty($options['avoid_highways']),

        ])->filter()->keys()->implode(',');



        // 1) Construir coordenadas en formato lon,lat

        $coords = collect($stops)

            ->map(function ($stop) {

                $lat = (float) $stop['latitude'];

                $lon = (float) $stop['longitude'];

                if (abs($lat) > 90 || abs($lon) > 180) {

                    throw new RuntimeException("Coordenadas fuera de rango: lat=$lat lon=$lon");

                }

                return $lon . ',' . $lat;

            })

            ->implode(';');



        $coords = preg_replace('/\s+/', '', trim($coords));



        try {

            // PASO A: optimizacion (driving) para ordenar paradas

            $optParams = [

                'source'       => 'any',

                'destination'  => 'any',

                'roundtrip'    => 'false',

                'overview'     => 'false',

                'access_token' => $token,

            ];

            if (!empty($exclude)) {

                $optParams['exclude'] = $exclude;

            }



            $optResponse = Http::timeout(30)->get(

                'https://api.mapbox.com/optimized-trips/v1/mapbox/driving/' . urlencode($coords),

                $optParams

            );



            if (

                !$optResponse->successful()

                || strtoupper((string) $optResponse->json('code')) !== 'OK'

            ) {

                throw new RuntimeException('Mapbox no pudo calcular el orden optimo.');

            }



            $trip = $optResponse->json('trips.0');

            if (!$trip) {

                throw new RuntimeException('Mapbox no devolvio datos de ruta.');

            }



            $waypointOrder = $trip['waypoint_order'] ?? [];

            $waypoints     = $optResponse->json('waypoints') ?? [];



            // Ordenar los indices de los stops segun waypoint_index

            $orderedIndices = collect($waypoints)

                ->map(function ($wp, $origIndex) {

                    return [

                        'orig' => $origIndex,

                        'pos'  => $wp['waypoint_index'] ?? PHP_INT_MAX,

                    ];

                })

                ->sortBy('pos')

                ->pluck('orig')

                ->values()

                ->all();



            // Fallback si faltan indices

            if (count($orderedIndices) !== count($stops)) {

                $orderedIndices = [0];

                foreach ($waypointOrder as $i) {

                    $orderedIndices[] = $i;

                }

                $orderedIndices[] = count($stops) - 1;

            }



            // Reordenar stops segun el orden optimizado

            $orderedStops = [];

            foreach ($orderedIndices as $idx) {

                if (!isset($stops[$idx])) {

                    continue;

                }

                $orderedStops[] = $stops[$idx];

            }



            // Coordenadas en el orden final

            $coordsOrdered = implode(';', array_map(function ($stop) {

                return $stop['longitude'] . ',' . $stop['latitude'];

            }, $orderedStops));



            // PASO B1: ruta base sin trafico

            $baseParams = [

                'geometries'   => 'geojson',

                'overview'     => 'full',

                'steps'        => 'true',

                'annotations'  => 'duration,distance',

                'access_token' => $token,

            ];

            if (!empty($exclude)) {

                $baseParams['exclude'] = $exclude;

            }



            $baseDirResponse = Http::timeout(30)->get(

                'https://api.mapbox.com/directions/v5/mapbox/driving/' . urlencode($coordsOrdered),

                $baseParams

            );



            $baseRoute = null;

            if ($baseDirResponse->successful() && !empty($baseDirResponse->json('routes'))) {

                $baseRoute = $baseDirResponse->json('routes.0');

            }



            // PASO B2: ruta con trafico

            $params = [

                'geometries'   => 'geojson',

                'overview'     => 'full',

                'steps'        => 'true',

                'annotations'  => 'duration,distance,congestion',

                'access_token' => $token,

            ];

            if (!empty($exclude)) {

                $params['exclude'] = $exclude;

            }



            $dirResponse = Http::timeout(30)->get(

                'https://api.mapbox.com/directions/v5/mapbox/driving-traffic/' . urlencode($coordsOrdered),

                $params

            );



            if (!$dirResponse->successful() || empty($dirResponse->json('routes'))) {

                throw new RuntimeException('Mapbox no devolvio rutas para el orden calculado.');

            }



            $route = $dirResponse->json('routes.0');



            return [

                'trip' => [

                    'distance' => $route['distance'] ?? null,

                    'duration' => $route['duration'] ?? null,

                    'geometry' => $route['geometry'] ?? null,

                    'legs' => $route['legs'] ?? [],

                    'ordered_indices' => $orderedIndices,

                    'ordered_stops' => $orderedStops,

                ],

                'waypoints' => $dirResponse->json('waypoints') ?? [],

                'ordered_stops' => $orderedStops,

                'options' => [

                    'avoid_tolls' => (bool) ($options['avoid_tolls'] ?? false),

                    'avoid_highways' => (bool) ($options['avoid_highways'] ?? false),

                ],

            ];

        } catch (\Throwable $e) {

            // Fallback: usar orden dado sin optimizar para no bloquear la generacion.

            $result = $this->requestDirectionsForOrdered($stops, $options);

            $result['optimization_warning'] = $e->getMessage();

            return $result;

        }

    }



    private function reorderStops(array $stops, array $trip, array $waypoints = []): array
    {
        // Si ya viene el orden final, úsalo
        if (isset($trip['ordered_stops']) && is_array($trip['ordered_stops']) && count($trip['ordered_stops']) === count($stops)) {
            return $trip['ordered_stops'];
        }

        if (isset($trip['ordered_indices']) && is_array($trip['ordered_indices'])) {
            $ordered = [];
            foreach ($trip['ordered_indices'] as $idx) {
                if (array_key_exists($idx, $stops)) {
                    $ordered[] = $stops[$idx];
                }
            }
            if (count($ordered) === count($stops)) {
                return $ordered;
            }
        }

        // Fallback a tu lógica: usar waypoint_order del "trip" (sólo del medio)
        $count = count($stops);
        if ($count <= 2) return $stops;

        $waypointOrder = $trip['waypoint_order'] ?? null;
        if (is_array($waypointOrder) && !empty($waypointOrder)) {
            $head = $stops[0];
            $tail = $stops[$count - 1];
            $middle = [];
            foreach ($waypointOrder as $originalIndex) {
                if (array_key_exists($originalIndex, $stops)) {
                    $middle[] = $stops[$originalIndex];
                }
            }
            if (!empty($middle)) {
                return array_merge([$head], $middle, [$tail]);
            }
        }

        return $stops;
    }

    private function summarizeTraffic(array $trip): array
    {
        $legs = collect($trip['legs'] ?? []);

        $congestionLevels = $legs
            ->flatMap(function ($leg) {
                $values = data_get($leg, 'annotation.congestion', []);
                if (!is_array($values)) {
                    $values = [$values];
                }

                return collect($values)->filter()->map(fn ($level) => strtolower((string) $level));
            })
            ->values();

        $counts = $congestionLevels->countBy()->all();

        $severityOrder = ['severe', 'heavy', 'moderate', 'low', 'unknown'];
        $summaryMap = [
            'severe'   => 'Ruta con congestión severa',
            'heavy'    => 'Tránsito muy cargado',
            'moderate' => 'Tránsito moderado',
            'low'      => 'Tránsito fluido',
            'unknown'  => 'Tránsito con datos incompletos',
        ];

        $level = null;
        foreach ($severityOrder as $candidate) {
            if (!empty($counts[$candidate])) {
                $level = $candidate;
                break;
            }
        }

        $summary = $summaryMap[$level] ?? 'Sin datos de congestión';

        return [
            'level'   => $level,
            'summary' => $summary,
            'report'  => [
                'congestion_counts' => $counts,
                'legs'              => $legs->map(function ($leg, $index) {
                    return [
                        'index'      => $index,
                        'distance'   => $leg['distance'] ?? null,
                        'duration'   => $leg['duration'] ?? null,
                        'annotation' => $leg['annotation'] ?? null,
                    ];
                })->all(),
            ],
        ];
    }

    private function extractFromContext(Collection $context, string $type): ?string
    {
        $match = $context->first(function ($item) use ($type) {
            return isset($item['id']) && Str::startsWith($item['id'], $type);
        });

        return $match['text'] ?? null;
    }

    
    private function logPayloadSize(array $payload): void
    {
        try {
            $encoded = json_encode($payload);
            if ($encoded === false) {
                return;
            }
            logger()->info('TrafficRoute payload size', [
                'raw_payload_bytes' => strlen($encoded),
            ]);
        } catch (\Throwable $e) {
            // ignore logging failures
        }
    }

private function mapboxToken(): string
    {
        $token = config('services.mapbox.token');
        if (!$token) {
            throw new RuntimeException('Falta configurar MAPBOX_ACCESS_TOKEN en el entorno.');
        }

        return $token;
    }

    private function shrinkPayload(array $payload, int $maxBytes = 900000): array
    {
        $encoded = json_encode($payload);
        if ($encoded !== false && strlen($encoded) <= $maxBytes) {
            return $payload;
        }

        $slim = [
            'trip' => [
                'distance' => data_get($payload, 'trip.distance'),
                'duration' => data_get($payload, 'trip.duration'),
                'legs' => data_get($payload, 'trip.legs', []),
                'ordered_indices' => data_get($payload, 'trip.ordered_indices', []),
            ],
            'options' => data_get($payload, 'options', []),
        ];

        $encodedSlim = json_encode($slim);
        if ($encodedSlim !== false && strlen($encodedSlim) <= $maxBytes) {
            return $slim;
        }

        $slim['trip']['legs'] = [];
        return $slim;
    }

    public function optimizeStopOrder(array $stops, array $options = []): array
    {
        if (count($stops) <= 1) {
            return [
                'stops' => $stops,
                'trip' => null,
                'warning' => null,
            ];
        }

        try {
            $payload = $this->requestOptimizedTrip($stops, $options);
            $reordered = $payload['ordered_stops'] ?? $this->fallbackReorderByDistance($stops);
            if (!is_array($reordered)) {
                $reordered = $this->fallbackReorderByDistance($stops);
            }
            return [
                'stops' => $reordered,
                'trip' => $payload['trip'] ?? null,
                'warning' => $payload['optimization_warning'] ?? null,
            ];
        } catch (\Throwable $e) {
            Log::warning('TrafficRouteService::optimizeStopOrder failed', [
                'message' => $e->getMessage(),
                'count' => count($stops),
            ]);
            return [
                'stops' => $this->fallbackReorderByDistance($stops),
                'trip' => null,
                'warning' => $e->getMessage(),
            ];
        }
    }

    private function markLooseStopsAsAssigned(TrafficRoute $route, array $inputStops, Collection $createdStops): void
    {
        // Mapa: loose_stop_id -> route_stop_id creado
        // OJO: esto asume que $createdStops está en el MISMO ORDEN que $inputStops (lo está).
        $pairs = collect($inputStops)->values()->map(function ($stop, $i) use ($createdStops) {
            $looseId = $stop['traffic_loose_stop_id'] ?? $stop['loose_stop_id'] ?? null;
            $looseId = $looseId ? (int) $looseId : null;

            $routeStop = $createdStops->get($i);
            $routeStopId = $routeStop ? (int) $routeStop->id : null;

            return [
                'loose_id' => $looseId,
                'route_stop_id' => $routeStopId,
            ];
        })->filter(fn ($x) => !empty($x['loose_id']) && !empty($x['route_stop_id']))->values();

        $allDebugData = collect($inputStops)->values()->map(function ($stop, $i) use ($createdStops) {
            $looseId = $stop['traffic_loose_stop_id'] ?? $stop['loose_stop_id'] ?? null;
            $routeStop = $createdStops->get($i);
            return [
                'index' => $i,
                'stop_traffic_loose_stop_id' => $stop['traffic_loose_stop_id'] ?? null,
                'stop_loose_stop_id' => $stop['loose_stop_id'] ?? null,
                'final_loose_id' => $looseId ? (int) $looseId : null,
                'route_stop_id' => $routeStop ? (int) $routeStop->id : null,
            ];
        })->values();

        \Log::info('TrafficRouteService: markLooseStopsAsAssigned FULL DEBUG', [
            'route_id' => $route->id,
            'all_pairs_before_filter' => $allDebugData->toArray(),
            'pairs_after_filter_count' => $pairs->count(),
            'pairs_after_filter' => $pairs->toArray(),
        ]);

        if ($pairs->isEmpty()) {
            return;
        }

        // Actualizar uno por uno para setear assigned_stop_id correctamente
        foreach ($pairs as $p) {
            TrafficLooseStop::where('id', $p['loose_id'])
                ->update([
                    'assigned_route_id' => $route->id,
                    'assigned_stop_id' => $p['route_stop_id'],
                    'assigned_at' => now(),
                ]);
        }
    }

}
