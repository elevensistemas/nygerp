<?php

namespace App\Services;

use App\Models\Transportista;
use Illuminate\Support\Collection;

class RouteSolver
{
    private const DISTANCE_LAST_WEIGHT = 0.1;      // peso para distancia al ultimo punto
    private const DISTANCE_CENTER_WEIGHT = 0.125;  // peso para acercar al centro de zona
    private const OUTSIDE_BASE_PENALTY = 1000;     // penalizacion si no esta dentro de zona
    private const OUTSIDE_SOFT_FACTOR = 0.2;       // reduce la penalizacion si hay zonas blandas

    /**
     * @param Collection $transportistas Collection of carriers with cost_efficiency and performance_weight
     * @param Collection $stops Collection of stops with keys: code, address, priority, lat, lng
     * @param int $maxStopsPerCarrier
     * @param string $strategy route|cost|weight
     * @param string $clustering none|address|priority
     * @return array [plans => Collection, warnings => array]
     */
    public function solve(Collection $transportistas, Collection $stops, int $maxStopsPerCarrier, string $strategy = 'route', string $clustering = 'none'): array
    {
        $maxStopsPerCarrier = max(1, $maxStopsPerCarrier);
        $warnings = [];

        $validStops = $stops->map(function ($stop) {
            $stop['lat'] = isset($stop['lat']) && is_numeric($stop['lat']) ? (float) $stop['lat'] : null;
            $stop['lng'] = isset($stop['lng']) && is_numeric($stop['lng']) ? (float) $stop['lng'] : null;
            return $stop;
        })->filter(function ($stop) use (&$warnings) {
            if ($stop['lat'] === null || $stop['lng'] === null) {
                $warnings[] = "Parada {$stop['code']} sin coordenadas, se omite.";
                return false;
            }
            return true;
        })->values();

        if ($transportistas->isEmpty()) {
            $warnings[] = 'No hay transportistas cargados.';
            return ['plans' => collect(), 'warnings' => $warnings];
        }

        if ($validStops->isEmpty()) {
            $warnings[] = 'No hay paradas con lat/lng para resolver.';
            return ['plans' => collect(), 'warnings' => $warnings];
        }

        $assignments = collect();
        $zoneCache = $this->buildZoneCache($transportistas);
        $fleet = $this->buildCarrierCapacity($transportistas);

        // Priorizamos paradas por prioridad y luego por codigo
        $priorities = ['Alta' => 1, 'Media' => 2, 'Baja' => 3];
        $sortedStops = $validStops->sortBy(function ($stop) use ($priorities) {
            $priority = $priorities[$stop['priority'] ?? 'Media'] ?? 2;
            return [$priority, $stop['code']];
        })->values();

        $needLineSplit = $clustering === 'address'
            && $transportistas->count() > 1
            && $sortedStops->count() > 35;

        if ($needLineSplit) {
            ['clusters' => $clusters, 'warnings' => $assignmentWarnings] = $this->assignLineSegments(
                $sortedStops,
                $transportistas,
                $maxStopsPerCarrier,
                $strategy,
                $zoneCache,
                $fleet
            );
        } else {
            ['clusters' => $clusters, 'warnings' => $assignmentWarnings] = $this->assignClusters(
                $sortedStops,
                $transportistas,
                $maxStopsPerCarrier,
                $clustering,
                $zoneCache,
                $fleet
            );
        }
        $warnings = array_merge($warnings, $assignmentWarnings);

        foreach ($clusters as $cluster) {
            $carrier = $cluster['carrier'] ?? null;
            if (!$carrier) {
                continue;
            }
            $this->assignCluster(
                $transportistas,
                $assignments,
                $cluster['stops'],
                $maxStopsPerCarrier,
                $strategy,
                $zoneCache,
                $fleet,
                $warnings,
                $carrier
            );
        }

        // Ordenamos rutas de cada carrier con nearest-neighbor basico, iniciando cerca del centro de zona si existe
        $plans = $assignments->values()->map(function ($assignment) use ($zoneCache, $fleet) {
            $zoneCenter = $this->primaryZoneCenter($assignment['carrier'], $zoneCache);
            $orderedStops = $this->orderRoute($assignment['stops'], $zoneCenter);
            $distance = $this->routeDistance($orderedStops);
            $capacity = $fleet[$assignment['carrier']->id] ?? [];

            return [
                'carrier' => $assignment['carrier'],
                'stops' => $orderedStops,
                'distance' => $distance,
                'cost_factor' => ($assignment['carrier']->cost_efficiency ?? 1) * max(1, $distance),
                'transporte_id' => $assignment['vehicle_id'] ?? ($capacity['vehicle_id'] ?? null),
                'total_weight_kg' => $assignment['total_weight'] ?? null,
                'total_volume_m3' => $assignment['total_volume'] ?? null,
                'capacity_weight_kg' => $capacity['weight_kg'] ?? null,
                'capacity_volume_m3' => $capacity['volume_m3'] ?? null,
            ];
        });

        return ['plans' => $plans, 'warnings' => $warnings];
    }

    public function eligibleTransportistas(Collection $transportistas, Collection $stops): Collection
    {
        if ($transportistas->isEmpty() || $stops->isEmpty()) {
            return collect();
        }
        $zoneCache = $this->buildZoneCache($transportistas);

        $eligible = collect();
        foreach ($transportistas as $carrier) {
            foreach ($stops as $stop) {
                $zoneInfo = $this->resolveZonePenalty($carrier, $stop, $zoneCache);
                if ($zoneInfo['allowed']) {
                    $eligible->push($carrier);
                    break;
                }
            }
        }

        return $eligible->unique('id')->values();
    }

    /**
     * @return Collection<Collection<array>>
     */
    private function buildClusters(Collection $stops, int $maxStopsPerCarrier, string $clustering): Collection
    {
        $maxStopsPerCarrier = max(1, $maxStopsPerCarrier);
        $clustering = strtolower(trim($clustering ?: 'none'));

        $hasClusterId = $stops->every(fn ($stop) => isset($stop['cluster_id']) && $stop['cluster_id'] !== null && $stop['cluster_id'] !== '');
        if ($hasClusterId) {
            return $stops
                ->groupBy(fn ($stop) => (string) $stop['cluster_id'])
                ->sortKeys()
                ->values()
                ->map(fn (Collection $group) => $group->values());
        }

        if ($clustering === 'priority') {
            $order = ['Alta' => 0, 'Media' => 1, 'Baja' => 2];
            $groups = $stops
                ->groupBy(fn ($stop) => $stop['priority'] ?? 'Media')
                ->sortBy(fn ($group, $priority) => $order[$priority] ?? 99)
                ->values()
                ->map(fn (Collection $group) => $group->values());

            $clusters = collect();
            foreach ($groups as $group) {
                $k = min(max(1, (int) ceil($group->count() / max(1, $maxStopsPerCarrier))), max(1, $group->count()));
                if ($k <= 1 || $group->count() <= 2) {
                    $clusters->push($group->values());
                    continue;
                }
                $sub = $this->kMeansClusters($group->values(), $k);
                foreach ($sub as $chunk) {
                    $clusters->push($chunk->values());
                }
            }

            return $clusters->values();
        }

        if ($clustering !== 'address') {
            return collect([$stops->values()]);
        }

        if ($stops->count() <= 2) {
            return collect([$stops->values()]);
        }

        $epsKm = 2.0;
        $minPts = 3;
        $clusters = $this->dbscanClusters($stops, $epsKm, $minPts);
        return $clusters
            ->map(function (Collection $group) {
                $priorities = ['Alta' => 1, 'Media' => 2, 'Baja' => 3];
                return $group->sortBy(function ($stop) use ($priorities) {
                    $priority = $priorities[$stop['priority'] ?? 'Media'] ?? 2;
                    return [$priority, $stop['code']];
                })->values();
            })
            ->sortByDesc(fn (Collection $group) => $group->count())
            ->values();
    }

    /**
     * DBSCAN por densidad usando Haversine (km).
     *
     * @return Collection<Collection<array>>
     */
    private function dbscanClusters(Collection $stops, float $epsKm, int $minPts): Collection
    {
        $points = $stops->values();
        $n = $points->count();
        if ($n === 0) {
            return collect();
        }

        $UNCLASSIFIED = 0;
        $NOISE = -1;
        $labels = array_fill(0, $n, $UNCLASSIFIED);
        $clusterId = 0;

        $regionQuery = function (int $idx) use ($points, $n, $epsKm): array {
            $p = $points[$idx];
            $neighbors = [];
            $lat1 = (float) ($p['lat'] ?? 0);
            $lng1 = (float) ($p['lng'] ?? 0);
            for ($j = 0; $j < $n; $j++) {
                $q = $points[$j];
                $lat2 = (float) ($q['lat'] ?? 0);
                $lng2 = (float) ($q['lng'] ?? 0);
                $d = $this->haversine($lat1, $lng1, $lat2, $lng2);
                if ($d <= $epsKm) {
                    $neighbors[] = $j;
                }
            }
            return $neighbors;
        };

        $expandCluster = function (int $idx, array $neighbors, int $cid) use (&$labels, $regionQuery, $minPts, $UNCLASSIFIED, $NOISE): void {
            $labels[$idx] = $cid;
            $queue = $neighbors;
            $seen = [];
            foreach ($queue as $q) {
                $seen[$q] = true;
            }

            while (!empty($queue)) {
                $nIdx = array_shift($queue);
                if ($labels[$nIdx] === $NOISE) {
                    $labels[$nIdx] = $cid;
                }
                if ($labels[$nIdx] !== $UNCLASSIFIED) {
                    continue;
                }
                $labels[$nIdx] = $cid;

                $nNeighbors = $regionQuery($nIdx);
                if (count($nNeighbors) >= $minPts) {
                    foreach ($nNeighbors as $x) {
                        if (!isset($seen[$x])) {
                            $seen[$x] = true;
                            $queue[] = $x;
                        }
                    }
                }
            }
        };

        for ($i = 0; $i < $n; $i++) {
            if ($labels[$i] !== $UNCLASSIFIED) {
                continue;
            }
            $neighbors = $regionQuery($i);
            if (count($neighbors) < $minPts) {
                $labels[$i] = $NOISE;
                continue;
            }
            $clusterId++;
            $expandCluster($i, $neighbors, $clusterId);
        }

        $clustersById = [];
        $noise = [];
        for ($i = 0; $i < $n; $i++) {
            $label = $labels[$i];
            if ($label === $NOISE) {
                $noise[] = $points[$i];
                continue;
            }
            $clustersById[$label] ??= [];
            $clustersById[$label][] = $points[$i];
        }

        $centroids = [];
        foreach ($clustersById as $id => $clusterPoints) {
            $sumLat = 0.0;
            $sumLng = 0.0;
            $cnt = 0;
            foreach ($clusterPoints as $p) {
                $sumLat += (float) ($p['lat'] ?? 0);
                $sumLng += (float) ($p['lng'] ?? 0);
                $cnt++;
            }
            if ($cnt > 0) {
                $centroids[$id] = ['lat' => $sumLat / $cnt, 'lng' => $sumLng / $cnt];
            }
        }

        $maxAssignKm = $epsKm * 3;
        $nextId = empty($clustersById) ? 1 : (max(array_keys($clustersById)) + 1);
        foreach ($noise as $p) {
            if (empty($clustersById)) {
                $clustersById[$nextId] = [$p];
                $centroids[$nextId] = ['lat' => (float) ($p['lat'] ?? 0), 'lng' => (float) ($p['lng'] ?? 0)];
                $nextId++;
                continue;
            }

            $bestId = null;
            $bestDist = INF;
            $lat = (float) ($p['lat'] ?? 0);
            $lng = (float) ($p['lng'] ?? 0);
            foreach ($centroids as $id => $c) {
                $d = $this->haversine($lat, $lng, (float) $c['lat'], (float) $c['lng']);
                if ($d < $bestDist) {
                    $bestDist = $d;
                    $bestId = $id;
                }
            }

            if ($bestId !== null && $bestDist <= $maxAssignKm) {
                $clustersById[$bestId][] = $p;
                // actualizar centroide simple
                $sumLat = 0.0;
                $sumLng = 0.0;
                $cnt = 0;
                foreach ($clustersById[$bestId] as $pp) {
                    $sumLat += (float) ($pp['lat'] ?? 0);
                    $sumLng += (float) ($pp['lng'] ?? 0);
                    $cnt++;
                }
                if ($cnt > 0) {
                    $centroids[$bestId] = ['lat' => $sumLat / $cnt, 'lng' => $sumLng / $cnt];
                }
            } else {
                $clustersById[$nextId] = [$p];
                $centroids[$nextId] = ['lat' => $lat, 'lng' => $lng];
                $nextId++;
            }
        }

        $clusters = collect($clustersById)
            ->map(function (array $clusterPoints, $id) {
                $clusterId = (int) $id;
                return collect($clusterPoints)->map(function ($stop) use ($clusterId) {
                    $stop['cluster_id'] = $clusterId;
                    return $stop;
                })->values();
            })
            ->values()
            ->sortByDesc(fn (Collection $group) => $group->count())
            ->values();

        return $clusters;
    }

    /**
     * Asigna clusters a transportistas según disponibilidad, zonas y capacidad.
     *
     * @return array{clusters: Collection<array>, warnings: array}
     */
    private function assignClusters(
        Collection $stops,
        Collection $transportistas,
        int $maxStopsPerCarrier,
        string $clustering,
        array $zoneCache,
        array $fleet
    ): array {
        if ($stops->isEmpty() || $transportistas->isEmpty()) {
            return ['clusters' => collect(), 'warnings' => []];
        }

        $availability = [];
        $totalAvailability = 0;
        $isActive = [];
        foreach ($transportistas as $carrier) {
            $weight = max(0.5, (float) ($carrier->performance_weight ?? 1));
            $available = (bool) ($carrier->is_active ?? true);
            $availability[$carrier->id] = $available ? $weight : 0.0;
            $totalAvailability += $availability[$carrier->id];
            $isActive[$carrier->id] = $available;
        }
        if ($totalAvailability <= 0) {
            $totalAvailability = 1.0;
        }

        $totalStops = $stops->count();
        $targets = [];
        foreach ($transportistas as $carrier) {
            $targets[$carrier->id] = $totalStops * ($availability[$carrier->id] / $totalAvailability);
        }

        $loads = array_fill_keys($transportistas->pluck('id')->all(), 0);
        $volumeLoads = array_fill_keys($transportistas->pluck('id')->all(), 0.0);
        $weightLoads = array_fill_keys($transportistas->pluck('id')->all(), 0.0);
        $lastStops = [];

        $clustersByCarrier = [];
        $warnings = [];

        foreach ($stops as $stop) {
            $best = null;
            $bestScore = INF;
            $stopWeight = $this->stopWeight($stop);
            $stopVolume = $this->stopVolume($stop);

            foreach ($transportistas as $carrier) {
                if (!($isActive[$carrier->id] ?? true)) {
                    continue;
                }
                if ($loads[$carrier->id] >= $maxStopsPerCarrier) {
                    continue;
                }

                $cap = $fleet[$carrier->id] ?? [];
                $capWeight = $cap['weight_kg'] ?? null;
                $capVolume = $cap['volume_m3'] ?? null;
                if ($capWeight !== null && $stopWeight !== null && ($weightLoads[$carrier->id] + $stopWeight) > $capWeight) {
                    continue;
                }
                if ($capVolume !== null && $stopVolume !== null && ($volumeLoads[$carrier->id] + $stopVolume) > $capVolume) {
                    continue;
                }

                $zoneInfo = $this->resolveZonePenalty($carrier, $stop, $zoneCache);
                if ($zoneInfo['allowed'] === false) {
                    continue;
                }

                $score = 0;
                $score += $zoneInfo['distance_to_center'] !== null ? ($zoneInfo['distance_to_center'] * self::DISTANCE_CENTER_WEIGHT) : 0;
                $score += ($zoneInfo['outside_penalty'] ?? 0);
                $load = $loads[$carrier->id];
                $distancePenalty = 0;
                if (isset($lastStops[$carrier->id])) {
                    $last = $lastStops[$carrier->id];
                    $distancePenalty = $this->haversine($last['lat'], $last['lng'], $stop['lat'], $stop['lng']);
                }
                $score += $distancePenalty * self::DISTANCE_LAST_WEIGHT;
                $target = $targets[$carrier->id] ?? 0;
                $imbalance = abs(($load + 1) - $target);
                $score += $imbalance * 0.3;

                if ($score < $bestScore) {
                    $bestScore = $score;
                    $best = $carrier;
                }
            }

            if (!$best) {
                $code = isset($stop['code']) ? $stop['code'] : (isset($stop['address']) ? $stop['address'] : 'parada');
                $warnings[] = "No se pudo asignar {$code} respetando zonas y capacidades.";
                continue;
            }

            $stop['cluster_id'] = $best->id;
            $lastStops[$best->id] = $stop;
            $loads[$best->id]++;
            if ($stopVolume !== null) {
                $volumeLoads[$best->id] += $stopVolume;
            }
            if ($stopWeight !== null) {
                $weightLoads[$best->id] += $stopWeight;
            }

            $clustersByCarrier[$best->id]['carrier'] = $best;
            $clustersByCarrier[$best->id]['stops'][] = $stop;
        }

        $clusters = collect($clustersByCarrier)->map(function ($entry) {
            $carrier = isset($entry['carrier']) ? $entry['carrier'] : null;
            $stops = isset($entry['stops']) ? collect($entry['stops']) : collect();
            return [
                'carrier' => $carrier,
                'stops' => $stops,
                'cluster_id' => $carrier ? $carrier->id : null,
            ];
        })->filter(function ($cluster) {
            return $cluster['carrier'] !== null && $cluster['stops']->isNotEmpty();
        })->values();

        return ['clusters' => $clusters, 'warnings' => $warnings];
    }

    private function assignLineSegments(
        Collection $stops,
        Collection $transportistas,
        int $maxStopsPerCarrier,
        string $strategy,
        array $zoneCache,
        array $fleet
    ): array {
        if ($stops->isEmpty() || $transportistas->isEmpty()) {
            return ['clusters' => collect(), 'warnings' => []];
        }

        $segments = $this->splitStopsIntoSegments($stops, max(1, $transportistas->count()));
        return $this->assignClustersFromSegments($segments, $transportistas, $maxStopsPerCarrier, $strategy, $zoneCache, $fleet);
    }

    private function assignClustersFromSegments(
        Collection $segments,
        Collection $transportistas,
        int $maxStopsPerCarrier,
        string $strategy,
        array $zoneCache,
        array $fleet
    ): array {
        $assignments = collect();
        $warnings = [];

        $carriers = $transportistas->values();
        foreach ($segments as $idx => $segment) {
            $cluster = collect($segment)->values();
            if ($cluster->isEmpty()) {
                continue;
            }
            $carrier = $carriers[min($idx, $carriers->count() - 1)] ?? null;
            $forced = $carrier;
            if (!$forced) {
                continue;
            }
            $this->assignCluster($transportistas, $assignments, $cluster, $maxStopsPerCarrier, $strategy, $zoneCache, $fleet, $warnings, $forced);
        }

        $clusters = $assignments->map(function ($assignment) {
            $carrier = $assignment['carrier'];
            return [
                'carrier' => $carrier,
                'stops' => $assignment['stops'],
                'cluster_id' => $carrier ? $carrier->id : null,
            ];
        })->values();

        return ['clusters' => $clusters, 'warnings' => $warnings];
    }

    private function splitStopsIntoSegments(Collection $stops, int $segments): Collection
    {
        $count = max(1, $stops->count());
        $segments = max(1, min($segments, $count));
        if ($segments <= 1) {
            return collect([$stops->values()]);
        }

        $meanLat = $stops->avg(fn ($stop) => $stop['lat'] ?? 0.0);
        $meanLng = $stops->avg(fn ($stop) => $stop['lng'] ?? 0.0);
        $covXX = 0.0;
        $covYY = 0.0;
        $covXY = 0.0;
        foreach ($stops as $stop) {
            $lat = (float) ($stop['lat'] ?? 0.0);
            $lng = (float) ($stop['lng'] ?? 0.0);
            $dLat = $lat - $meanLat;
            $dLng = $lng - $meanLng;
            $covXX += $dLat * $dLat;
            $covYY += $dLng * $dLng;
            $covXY += $dLat * $dLng;
        }

        $theta = 0.5 * atan2(2 * $covXY, $covXX - $covYY);
        $cos = cos($theta);
        $sin = sin($theta);

        $sorted = $stops
            ->map(fn ($stop) => [
                'stop' => $stop,
                'projection' => (($stop['lat'] ?? 0.0) - $meanLat) * $cos + (($stop['lng'] ?? 0.0) - $meanLng) * $sin,
            ])
            ->sortBy(fn ($entry) => $entry['projection'])
            ->values()
            ->map(fn ($entry) => $entry['stop']);

        $perSegment = (int) ceil($count / $segments);
        $result = collect();
        for ($i = 0; $i < $segments; $i++) {
            $slice = $sorted->slice($i * $perSegment, $perSegment)->values();
            if ($slice->isNotEmpty()) {
                $result->push($slice);
            }
        }

        if ($result->isEmpty()) {
            $result->push($sorted);
        }

        return $result;
    }

    private function assignCluster(
        Collection $transportistas,
        Collection $assignments,
        Collection $cluster,
        int $maxStopsPerCarrier,
        string $strategy,
        array $zoneCache,
        array $fleet,
        array &$warnings,
        ?Transportista $forcedCarrier = null
    ): void {
        $cluster = $cluster->values();
        if ($cluster->isEmpty()) {
            return;
        }

        $bestCarrier = $forcedCarrier ?? $this->pickCarrierForCluster($transportistas, $assignments, $cluster, $maxStopsPerCarrier, $strategy, $zoneCache, $fleet);
        if (!$bestCarrier) {
            if ($cluster->count() > 1) {
                // fallback: partir y reintentar para destrabar combinaciones de zonas/capacidad
                $subClusters = $this->kMeansClusters($cluster, 2);
                $subClusters->each(function (Collection $sub) use ($transportistas, $assignments, $maxStopsPerCarrier, $strategy, $zoneCache, $fleet, &$warnings) {
                    $this->assignCluster($transportistas, $assignments, $sub, $maxStopsPerCarrier, $strategy, $zoneCache, $fleet, $warnings);
                });
                return;
            }

            $stop = $cluster->first();
            $code = $stop['code'] ?? ($stop['address'] ?? 'parada');
            $warnings[] = "No se pudo asignar {$code} por falta de capacidad o zonas.";
            return;
        }

        $current = $assignments->get($bestCarrier->id, [
            'carrier' => $bestCarrier,
            'stops' => collect(),
            'total_weight' => 0,
            'total_volume' => 0,
        ]);

        $remaining = max(0, $maxStopsPerCarrier - $current['stops']->count());
        if ($remaining <= 0) {
            $warnings[] = "Carrier {$bestCarrier->name} alcanzo el maximo de paradas.";
            return;
        }

        $take = min($remaining, $cluster->count());
        $cluster->take($take)->each(function ($stop) use (&$current) {
            $current['stops']->push($stop);
            $current['total_weight'] += $this->stopWeight($stop) ?? 0;
            $current['total_volume'] += $this->stopVolume($stop) ?? 0;
        });

        $current['vehicle_id'] = $fleet[$bestCarrier->id]['vehicle_id'] ?? null;
        $current['capacity_weight'] = $fleet[$bestCarrier->id]['weight_kg'] ?? null;
        $current['capacity_volume'] = $fleet[$bestCarrier->id]['volume_m3'] ?? null;
        $assignments->put($bestCarrier->id, $current);

        $left = $cluster->slice($take)->values();
        if ($left->isNotEmpty()) {
            // reintentar con el remanente (otro carrier o mas de uno)
            $this->assignCluster($transportistas, $assignments, $left, $maxStopsPerCarrier, $strategy, $zoneCache, $fleet, $warnings);
        }
    }

    private function pickCarrier(Collection $transportistas, Collection $assignments, array $stop, int $maxStopsPerCarrier, string $strategy, array $zoneCache, array $fleet)
    {
        $candidates = $transportistas->map(function ($carrier) use ($assignments, $stop, $maxStopsPerCarrier, $strategy, $zoneCache, $fleet) {
            $current = $assignments->get($carrier->id, ['stops' => collect()]);
            $load = $current['stops']->count();
            if ($load >= $maxStopsPerCarrier) {
                return null;
            }

            $capacity = $fleet[$carrier->id] ?? [];
            $capWeight = $capacity['weight_kg'] ?? null;
            $capVolume = $capacity['volume_m3'] ?? null;
            $currentWeight = $current['total_weight'] ?? 0;
            $currentVolume = $current['total_volume'] ?? 0;

            $stopWeight = $this->stopWeight($stop);
            $stopVolume = $this->stopVolume($stop);

            if ($capWeight !== null && $stopWeight !== null && ($currentWeight + $stopWeight) > $capWeight) {
                return null;
            }
            if ($capVolume !== null && $stopVolume !== null && ($currentVolume + $stopVolume) > $capVolume) {
                return null;
            }

            $lastStop = $current['stops']->last();
            $distance = $lastStop ? $this->haversine($lastStop['lat'], $lastStop['lng'], $stop['lat'], $stop['lng']) : 0;

            // Score base por carga y costos
            $score = $load;
            if ($strategy === 'cost') {
                $score *= max(0.01, $carrier->cost_efficiency ?? 0);
            } elseif ($strategy === 'weight') {
                $score /= max(0.1, $carrier->performance_weight ?? 1);
            } else { // route
                $score += $distance * self::DISTANCE_LAST_WEIGHT;
            }

            // Penalizaciones/ajustes por cobertura de zona
            $zoneInfo = $this->resolveZonePenalty($carrier, $stop, $zoneCache);
            if ($zoneInfo['allowed'] === false) {
                return null;
            }
            if ($zoneInfo['distance_to_center'] !== null) {
                $score += $zoneInfo['distance_to_center'] * self::DISTANCE_CENTER_WEIGHT;
            }
            if ($zoneInfo['outside_penalty'] > 0) {
                $score += $zoneInfo['outside_penalty'];
            }

            // Pequeña penalizacion si vamos llenando el vehiculo para balancear carga
            if ($capWeight !== null && $capWeight > 0) {
                $score += (($currentWeight + ($stopWeight ?? 0)) / $capWeight) * 0.05;
            }
            if ($capVolume !== null && $capVolume > 0) {
                $score += (($currentVolume + ($stopVolume ?? 0)) / $capVolume) * 0.05;
            }

            return [
                'carrier' => $carrier,
                'score' => $score,
            ];
        })->filter()->sortBy('score')->first();

        return $candidates['carrier'] ?? null;
    }

    private function pickCarrierForCluster(
        Collection $transportistas,
        Collection $assignments,
        Collection $cluster,
        int $maxStopsPerCarrier,
        string $strategy,
        array $zoneCache,
        array $fleet
    ) {
        $centroid = $this->clusterCentroid($cluster);

        $best = $transportistas->map(function ($carrier) use ($assignments, $cluster, $centroid, $maxStopsPerCarrier, $strategy, $zoneCache, $fleet) {
            $current = $assignments->get($carrier->id, ['stops' => collect()]);
            $load = $current['stops']->count();
            $remainingStops = $maxStopsPerCarrier - $load;
            if ($remainingStops <= 0) {
                return null;
            }

            $candidateStops = $cluster->take(min($remainingStops, $cluster->count()))->values();
            $clusterWeight = $candidateStops->sum(fn ($s) => $this->stopWeight($s) ?? 0);
            $clusterVolume = $candidateStops->sum(fn ($s) => $this->stopVolume($s) ?? 0);

            $capacity = $fleet[$carrier->id] ?? [];
            $capWeight = $capacity['weight_kg'] ?? null;
            $capVolume = $capacity['volume_m3'] ?? null;
            $currentWeight = $current['total_weight'] ?? 0;
            $currentVolume = $current['total_volume'] ?? 0;

            if ($capWeight !== null && ($currentWeight + $clusterWeight) > $capWeight) {
                return null;
            }
            if ($capVolume !== null && ($currentVolume + $clusterVolume) > $capVolume) {
                return null;
            }

            $lastStop = $current['stops']->last();
            $distanceToCentroid = 0;
            if ($lastStop && $centroid) {
                $distanceToCentroid = $this->haversine($lastStop['lat'], $lastStop['lng'], $centroid['lat'], $centroid['lng']);
            }

            // zona: promediamos penalizaciones y bloqueamos si alguna es hard-fail
            $zoneDistance = 0;
            $outsidePenalty = 0;
            foreach ($candidateStops as $stop) {
                $zoneInfo = $this->resolveZonePenalty($carrier, $stop, $zoneCache);
                if ($zoneInfo['allowed'] === false) {
                    return null;
                }
                if ($zoneInfo['distance_to_center'] !== null) {
                    $zoneDistance += (float) $zoneInfo['distance_to_center'];
                }
                if (is_finite($zoneInfo['outside_penalty'] ?? 0)) {
                    $outsidePenalty += (float) ($zoneInfo['outside_penalty'] ?? 0);
                } else {
                    return null;
                }
            }

            $n = max(1, $candidateStops->count());
            $avgZoneDistance = $zoneDistance / $n;
            $avgOutsidePenalty = $outsidePenalty / $n;

            // score base: evitamos forzar repartos parejos; el clustering ya lo hace
            $score = $load * 0.25;
            if ($strategy === 'cost') {
                $score *= max(0.01, $carrier->cost_efficiency ?? 0);
            } elseif ($strategy === 'weight') {
                $score /= max(0.1, $carrier->performance_weight ?? 1);
            } else { // route
                $score += $distanceToCentroid * self::DISTANCE_LAST_WEIGHT;
            }

            $score += $avgZoneDistance * self::DISTANCE_CENTER_WEIGHT;
            $score += $avgOutsidePenalty;

            if ($capWeight !== null && $capWeight > 0) {
                $score += (($currentWeight + $clusterWeight) / $capWeight) * 0.05;
            }
            if ($capVolume !== null && $capVolume > 0) {
                $score += (($currentVolume + $clusterVolume) / $capVolume) * 0.05;
            }

            return ['carrier' => $carrier, 'score' => $score];
        })->filter()->sortBy('score')->first();

        return $best['carrier'] ?? null;
    }

    /**
     * @return array{lat: float, lng: float}|null
     */
    private function clusterCentroid(Collection $cluster): ?array
    {
        $n = 0;
        $sumLat = 0.0;
        $sumLng = 0.0;
        foreach ($cluster as $stop) {
            if (!isset($stop['lat'], $stop['lng'])) {
                continue;
            }
            $lat = (float) $stop['lat'];
            $lng = (float) $stop['lng'];
            if (!is_finite($lat) || !is_finite($lng)) {
                continue;
            }
            $sumLat += $lat;
            $sumLng += $lng;
            $n++;
        }
        if ($n === 0) {
            return null;
        }
        return ['lat' => $sumLat / $n, 'lng' => $sumLng / $n];
    }

    /**
     * K-means simple sobre lat/lng (aprox euclidiana) para particionar por proximidad.
     *
     * @return Collection<Collection<array>>
     */
    private function kMeansClusters(Collection $stops, int $k): Collection
    {
        $points = $stops->values();
        $n = $points->count();
        $k = max(1, min($k, $n));
        if ($k === 1) {
            return collect([$points]);
        }

        $centers = $this->kMeansPlusPlusInit($points, $k);
        $k = count($centers);
        if ($k === 0) {
            return collect([$points]);
        }

        $assignments = array_fill(0, $n, 0);
        for ($iter = 0; $iter < 15; $iter++) {
            $changed = false;

            // asignar puntos a centro mas cercano
            for ($i = 0; $i < $n; $i++) {
                $p = $points[$i];
                $best = 0;
                $bestDist = INF;
                for ($c = 0; $c < $k; $c++) {
                    $d = $this->squaredDistance($p, $centers[$c]);
                    if ($d < $bestDist) {
                        $bestDist = $d;
                        $best = $c;
                    }
                }
                if ($assignments[$i] !== $best) {
                    $assignments[$i] = $best;
                    $changed = true;
                }
            }

            // recomputar centros
            $sum = array_fill(0, $k, ['lat' => 0.0, 'lng' => 0.0, 'count' => 0]);
            for ($i = 0; $i < $n; $i++) {
                $c = $assignments[$i];
                $sum[$c]['lat'] += (float) $points[$i]['lat'];
                $sum[$c]['lng'] += (float) $points[$i]['lng'];
                $sum[$c]['count']++;
            }
            for ($c = 0; $c < $k; $c++) {
                if ($sum[$c]['count'] === 0) {
                    continue;
                }
                $centers[$c] = [
                    'lat' => $sum[$c]['lat'] / $sum[$c]['count'],
                    'lng' => $sum[$c]['lng'] / $sum[$c]['count'],
                ];
            }

            if (!$changed) {
                break;
            }
        }

        $clusters = array_fill(0, $k, []);
        for ($i = 0; $i < $n; $i++) {
            $clusters[$assignments[$i]][] = $points[$i];
        }

        return collect(array_values(array_filter($clusters, fn ($group) => count($group) > 0)))
            ->map(fn ($group) => collect($group)->values())
            ->values();
    }

    /**
     * @return array<int, array{lat: float, lng: float}>
     */
    private function kMeansPlusPlusInit(Collection $points, int $k): array
    {
        $n = $points->count();

        // primer centro: punto mas "denso" (mas vecinos cercanos) para alinear con el criterio pedido
        $bestIdx = 0;
        $bestNeighbors = -1;
        $radius = 0.012; // ~1.3km en lat (aprox)
        for ($i = 0; $i < $n; $i++) {
            $pi = $points[$i];
            $neighbors = 0;
            for ($j = 0; $j < $n; $j++) {
                if ($i === $j) continue;
                $pj = $points[$j];
                $dLat = abs((float) $pi['lat'] - (float) $pj['lat']);
                $dLng = abs((float) $pi['lng'] - (float) $pj['lng']);
                if ($dLat <= $radius && $dLng <= $radius) {
                    $neighbors++;
                }
            }
            if ($neighbors > $bestNeighbors) {
                $bestNeighbors = $neighbors;
                $bestIdx = $i;
            }
        }

        $centers = [[
            'lat' => (float) $points[$bestIdx]['lat'],
            'lng' => (float) $points[$bestIdx]['lng'],
        ]];

        while (count($centers) < $k) {
            $distances = [];
            $sum = 0.0;
            for ($i = 0; $i < $n; $i++) {
                $p = $points[$i];
                $min = INF;
                foreach ($centers as $c) {
                    $min = min($min, $this->squaredDistance($p, $c));
                }
                $distances[$i] = $min;
                $sum += $min;
            }

            if ($sum <= 0) {
                // puntos iguales; rellenar con los primeros distintos
                for ($i = 0; $i < $n && count($centers) < $k; $i++) {
                    $candidate = ['lat' => (float) $points[$i]['lat'], 'lng' => (float) $points[$i]['lng']];
                    $exists = false;
                    foreach ($centers as $c) {
                        if ($this->squaredDistance($candidate, $c) < 1e-12) {
                            $exists = true;
                            break;
                        }
                    }
                    if (!$exists) {
                        $centers[] = $candidate;
                    }
                }
                break;
            }

            $r = (mt_rand() / mt_getrandmax()) * $sum;
            $acc = 0.0;
            $chosen = 0;
            for ($i = 0; $i < $n; $i++) {
                $acc += $distances[$i];
                if ($acc >= $r) {
                    $chosen = $i;
                    break;
                }
            }
            $centers[] = [
                'lat' => (float) $points[$chosen]['lat'],
                'lng' => (float) $points[$chosen]['lng'],
            ];
        }

        return $centers;
    }

    private function squaredDistance(array $a, array $b): float
    {
        $dLat = ((float) $a['lat']) - ((float) $b['lat']);
        $dLng = ((float) $a['lng']) - ((float) $b['lng']);
        return $dLat * $dLat + $dLng * $dLng;
    }

    private function orderRoute(Collection $stops, ?array $startingPoint = null): Collection
    {
        if ($stops->count() <= 2) {
            return $stops;
        }

        $remaining = $stops->values();
        $route = collect();

        if ($startingPoint) {
            $current = $remaining->sortBy(function ($stop) use ($startingPoint) {
                return $this->haversine($startingPoint['lat'], $startingPoint['lng'], $stop['lat'], $stop['lng']);
            })->shift();
        } else {
            $current = $remaining->shift();
        }
        $route->push($current);

        while ($remaining->isNotEmpty()) {
            $next = $remaining->sortBy(function ($stop) use ($current) {
                return $this->haversine($current['lat'], $current['lng'], $stop['lat'], $stop['lng']);
            })->shift();

            $route->push($next);
            $current = $next;
            $remaining = $remaining->filter(fn ($s) => $s !== $next)->values();
        }

        return $route;
    }

    private function routeDistance(Collection $stops): float
    {
        if ($stops->count() < 2) {
            return 0.0;
        }

        $distance = 0;
        for ($i = 0; $i < $stops->count() - 1; $i++) {
            $a = $stops[$i];
            $b = $stops[$i + 1];
            $distance += $this->haversine($a['lat'], $a['lng'], $b['lat'], $b['lng']);
        }

        return round($distance, 2);
    }

    private function stopWeight(array $stop): ?float
    {
        if (isset($stop['effective_weight']) && is_numeric($stop['effective_weight'])) {
            return max(0, (float) $stop['effective_weight']);
        }
        $actual = isset($stop['weight_actual']) && is_numeric($stop['weight_actual']) ? (float) $stop['weight_actual'] : null;
        $vol = isset($stop['weight_volumetric']) && is_numeric($stop['weight_volumetric']) ? (float) $stop['weight_volumetric'] : null;

        if ($actual === null && $vol === null) {
            return null;
        }

        return max(0, max($actual ?? 0, $vol ?? 0));
    }

    private function stopVolume(array $stop): ?float
    {
        if (isset($stop['volume_m3']) && is_numeric($stop['volume_m3'])) {
            $v = (float) $stop['volume_m3'];
            return $v >= 0 ? $v : null;
        }

        $l = isset($stop['length']) ? (float) $stop['length'] : null;
        $w = isset($stop['width']) ? (float) $stop['width'] : null;
        $h = isset($stop['height']) ? (float) $stop['height'] : null;
        if ($l !== null && $w !== null && $h !== null && $l > 0 && $w > 0 && $h > 0) {
            return ($l * $w * $h) / 1000000; // cm^3 a m^3
        }

        return null;
    }

    private function buildZoneCache(Collection $transportistas): array
    {
        $cache = [];
        foreach ($transportistas as $carrier) {
            if (method_exists($carrier, 'relationLoaded') && !$carrier->relationLoaded('zones')) {
                $carrier->load('zones');
            }
            if (method_exists($carrier, 'relationLoaded') && !$carrier->relationLoaded('sharedZones')) {
                $carrier->load('sharedZones');
            }
            $cache[$carrier->id] = $carrier->allZones();
        }

        return $cache;
    }

    private function buildCarrierCapacity(Collection $transportistas): array
    {
        $fleet = [];

        foreach ($transportistas as $carrier) {
            if (method_exists($carrier, 'relationLoaded') && !$carrier->relationLoaded('transportes')) {
                $carrier->load('transportes');
            }
            $vehicle = $carrier->transportes->firstWhere('is_default', true)
                ?? $carrier->transportes->firstWhere('is_active', true)
                ?? $carrier->transportes->first();

            $capacityKg = $vehicle && isset($vehicle->capacity_kg) ? (float) $vehicle->capacity_kg : null;
            $volumeM3 = null;
            if ($vehicle) {
                if (isset($vehicle->volume_m3) && is_numeric($vehicle->volume_m3)) {
                    $volumeM3 = (float) $vehicle->volume_m3;
                } elseif (isset($vehicle->length_cm, $vehicle->width_cm, $vehicle->height_cm)
                    && is_numeric($vehicle->length_cm) && is_numeric($vehicle->width_cm) && is_numeric($vehicle->height_cm)) {
                    $volumeM3 = ($vehicle->length_cm * $vehicle->width_cm * $vehicle->height_cm) / 1000000;
                }
            }

            $fleet[$carrier->id] = [
                'vehicle_id' => $vehicle->id ?? null,
                'weight_kg' => $capacityKg,
                'volume_m3' => $volumeM3,
            ];
        }

        return $fleet;
    }

    private function primaryZoneCenter(Transportista $carrier, array $zoneCache): ?array
    {
        /** @var Collection|null $zones */
        $zones = $zoneCache[$carrier->id] ?? null;
        if (!$zones || $zones->isEmpty()) {
            return null;
        }

        $primary = $zones->sortBy(function ($zone) {
            return $zone->priority === 'primary' ? 0 : 1;
        })->first();

        if (!$primary || $primary->center_lat === null || $primary->center_lng === null) {
            return null;
        }

        return ['lat' => $primary->center_lat, 'lng' => $primary->center_lng];
    }

    private function resolveZonePenalty(Transportista $carrier, array $stop, array $zoneCache): array
    {
        $zones = $zoneCache[$carrier->id] ?? collect();
        if ($zones->isEmpty()) {
            return [
                'distance_to_center' => null,
                'outside_penalty' => 0,
                'allowed' => true,
            ];
        }

        $lat = (float) $stop['lat'];
        $lng = (float) $stop['lng'];
        $bestZone = null;
        $bestPriority = 99;
        $distanceToCenter = null;
        $nearestDistance = null;
        $hasSoftZone = false;
        $hasHardZone = false;

        foreach ($zones as $zone) {
            if ($zone->is_soft) {
                $hasSoftZone = true;
            } else {
                $hasHardZone = true;
            }
            $priority = $zone->priority === 'primary' ? 0 : 1;
            $contains = $zone->containsPoint($lat, $lng);
            $distCenter = $zone->distanceToCenter($lat, $lng);
            if ($contains) {
                if ($priority < $bestPriority) {
                    $bestPriority = $priority;
                    $bestZone = $zone;
                    $distanceToCenter = $distCenter;
                } elseif ($priority === $bestPriority && $distCenter !== null && ($distanceToCenter === null || $distCenter < $distanceToCenter)) {
                    $bestZone = $zone;
                    $distanceToCenter = $distCenter;
                }
            }
            if ($distCenter !== null) {
                $nearestDistance = $nearestDistance === null ? $distCenter : min($nearestDistance, $distCenter);
            }
        }

        if ($bestZone) {
            return [
                'distance_to_center' => $distanceToCenter,
                'outside_penalty' => 0,
                'allowed' => true,
            ];
        }

        // Si no entra en ninguna zona
        if ($hasHardZone && !$hasSoftZone) {
            // zonas duras y ninguna blanda: no asignar
            return [
                'distance_to_center' => $nearestDistance,
                'outside_penalty' => INF,
                'allowed' => false,
            ];
        }

        // Si hay zonas blandas, penalizar para desincentivar pero permitir
        $basePenalty = self::OUTSIDE_BASE_PENALTY;
        $softFactor = $hasSoftZone ? self::OUTSIDE_SOFT_FACTOR : 1.0;
        $distancePenalty = $nearestDistance !== null ? $nearestDistance * 2 : 500;

        return [
            'distance_to_center' => $nearestDistance,
            'outside_penalty' => $basePenalty * $softFactor + $distancePenalty,
            'allowed' => true,
        ];
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
}
