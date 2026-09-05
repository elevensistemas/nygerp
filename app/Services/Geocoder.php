<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

class Geocoder
{
    /**
    * @return array{0: Collection, 1: array}
    */
    public function geocodeStops(
        Collection $stops,
        string $provider = 'mapbox',
        bool $keepOriginalAddress = false,
        bool $strict = false
    ): array
    {
        $provider = in_array($provider, ['mapbox', 'google', 'openstreet'], true) ? $provider : 'openstreet';
        $warnings = [];
        $cache = [];

        $resolved = $stops->map(function ($stop) use (&$warnings, &$cache, $provider, $keepOriginalAddress, $strict) {
            $existingLat = $stop['lat'] ?? $stop['latitude'] ?? null;
            $existingLng = $stop['lng'] ?? $stop['longitude'] ?? null;

            if (is_numeric($existingLat) && is_numeric($existingLng)) {
                $lat = (float) $existingLat;
                $lng = (float) $existingLng;
                $stop['lat'] = $lat;
                $stop['lng'] = $lng;
                $stop['latitude'] = $lat;
                $stop['longitude'] = $lng;
                return $stop;
            }

            $address = trim((string) ($stop['address'] ?? ''));
            if ($address === '') {
                $warnings[] = "Parada {$stop['code']} sin direccion, se omite.";
                return $stop;
            }

            if (isset($cache[$address])) {
                [$lat, $lng, $warning, $resolvedAddress] = $cache[$address];
                if ($warning) {
                    $warnings[] = $warning;
                }
                if ($resolvedAddress && !$keepOriginalAddress) {
                    $stop['address'] = $resolvedAddress;
                }
                if ($lat !== null && $lng !== null) {
                    $stop['lat'] = $lat;
                    $stop['lng'] = $lng;
                    $stop['latitude'] = $lat;
                    $stop['longitude'] = $lng;
                }
                return $stop;
            }
            if ($provider === 'google') {
                $geo = $this->geocodeGoogle($address, $strict);
            } elseif ($provider === 'mapbox') {
                $geo = $this->geocodeMapbox($address, $strict);
            } else {
                $geo = $this->geocodeOpenStreet($address, $strict);
            }

            if ($geo['lat'] === null || $geo['lng'] === null) {
                $warnings[] = $geo['warning'] ?? "No se pudo geocodificar {$address}";
            }

            $cache[$address] = [$geo['lat'], $geo['lng'], $geo['warning'] ?? null, $geo['address'] ?? null];
            if (!empty($geo['address']) && !$keepOriginalAddress) {
                $stop['address'] = $geo['address'];
            }
            $stop['lat'] = $geo['lat'];
            $stop['lng'] = $geo['lng'];
            $stop['latitude'] = $geo['lat'];
            $stop['longitude'] = $geo['lng'];

            return $stop;
        });

        return [$resolved, $warnings];
    }

    private function geocodeOpenStreet(string $address, bool $strict = false): array
    {
        static $lastOpenStreetCall = 0.0;
        $now = microtime(true);
        $elapsed = $now - $lastOpenStreetCall;
        if ($elapsed < 1.1) {
            usleep((int) ((1.1 - $elapsed) * 1_000_000));
        }
        $lastOpenStreetCall = microtime(true);

        $sanitized = $this->sanitizeAddress($address);
        $query = $sanitized !== '' ? $sanitized : $address;

        $userAgent = config('services.openstreet.user_agent')
            ?? config('app.name', 'ERP') . ' geocoder (+https://github.com/)';
        $acceptLanguage = config('app.locale', 'es');

        $response = Http::retry(2, 1500)
            ->timeout(20)
            ->withHeaders([
                'User-Agent' => $userAgent,
                'Accept-Language' => $acceptLanguage,
            ])
            ->get('https://nominatim.openstreetmap.org/search', [
                'q' => $query,
                'format' => 'jsonv2',
                'addressdetails' => 1,
                'limit' => 5,
                'countrycodes' => 'ar',
                'accept-language' => 'es',
            ]);

        if (!$response->ok()) {
            return ['lat' => null, 'lng' => null, 'warning' => "OpenStreetMap error {$response->status()} para {$address}"];
        }

        $results = $response->json();
        if (!is_array($results) || empty($results)) {
            return ['lat' => null, 'lng' => null, 'warning' => "OpenStreetMap no encontro coordenadas para {$address}"];
        }

        $best = $results[0];
        $lat = isset($best['lat']) ? (float) $best['lat'] : null;
        $lng = isset($best['lon']) ? (float) $best['lon'] : null;
        $resolved = $best['display_name'] ?? $address;
        $importance = isset($best['importance']) ? (float) $best['importance'] : null;
        $class = $best['class'] ?? $best['category'] ?? '';

        if ($strict) {
            $allowedClasses = ['place', 'building', 'amenity', 'highway'];
            $isAllowed = in_array($class, $allowedClasses, true);
            if (!$isAllowed) {
                return ['lat' => null, 'lng' => null, 'warning' => "OpenStreetMap no encontro direccion confiable para {$address}"];
            }
        }

        if ($lat === null || $lng === null) {
            return ['lat' => null, 'lng' => null, 'warning' => "OpenStreetMap no devolvio coordenadas para {$address}"];
        }

        return [
          'lat' => $lat,
          'lng' => $lng,
          'address' => $resolved,
          'warning' => null,
        ];
    }

    private function geocodeMapbox(string $address, bool $strict = false): array
    {
        $token = config('services.mapbox.token') ?? env('MAPBOX_TOKEN');
        if (!$token) {
            return ['lat' => null, 'lng' => null, 'warning' => 'Configura MAPBOX_TOKEN para geocodificar.'];
        }

        $sanitized = $this->sanitizeAddress($address);
        logger()->info('Geocoder request', [
            'provider' => 'mapbox',
            'address' => $address,
            'sanitized' => $sanitized,
            'strict' => $strict,
        ]);
        $url = 'https://api.mapbox.com/geocoding/v5/mapbox.places/' . urlencode($sanitized) . '.json';
        $baseParams = [
            'access_token' => $token,
            'limit' => 5,
            'language' => 'es',
            'country' => 'AR',
        ];
        $response = Http::timeout(8)->get($url, $baseParams);

        if ((!$response->ok() || empty($response->json('features.0.center'))) && $sanitized !== $address) {
            $url = 'https://api.mapbox.com/geocoding/v5/mapbox.places/' . urlencode($address) . '.json';
            $response = Http::timeout(8)->get($url, $baseParams);
        }

        if (!$response->ok()) {
            return ['lat' => null, 'lng' => null, 'warning' => "Mapbox error {$response->status()} para {$address}"];
        }

        $data = $response->json();
        $feature = $this->pickBestFeature($data['features'] ?? []);
        $center = $feature['center'] ?? null;
        $resolvedAddress = $feature['place_name'] ?? $address;
        $types = $feature['place_type'] ?? [];
        $relevance = $feature['relevance'] ?? null;

        if ($strict) {
            if (!in_array('address', $types, true)) {
                return ['lat' => null, 'lng' => null, 'warning' => "Mapbox no encontro direccion exacta para {$address}"];
            }
            if (is_numeric($relevance) && (float) $relevance < 0.8) {
                return ['lat' => null, 'lng' => null, 'warning' => "Mapbox no encontro direccion confiable para {$address}"];
            }
        }

        if (!is_array($center) || count($center) < 2) {
            return ['lat' => null, 'lng' => null, 'warning' => "Mapbox no devolvio coordenadas para {$address}"];
        }

        return [
            'lat' => (float) $center[1],
            'lng' => (float) $center[0],
            'address' => $resolvedAddress,
            'warning' => null,
        ];
    }

    private function geocodeGoogle(string $address, bool $strict = false): array
    {
        $key = config('services.google_maps.key') ?? env('GOOGLE_MAPS_KEY');
        if (!$key) {
            return ['lat' => null, 'lng' => null, 'warning' => 'Configura GOOGLE_MAPS_KEY para geocodificar.'];
        }

        logger()->info('Geocoder request', [
            'provider' => 'google',
            'address' => $address,
            'strict' => $strict,
        ]);
        $url = 'https://maps.googleapis.com/maps/api/geocode/json';
        $response = Http::timeout(8)->get($url, [
            'address' => $address,
            'language' => 'es',
            'key' => $key,
        ]);

        if (!$response->ok()) {
            return ['lat' => null, 'lng' => null, 'warning' => "Google error {$response->status()} para {$address}"];
        }

        $data = $response->json();
        $first = $data['results'][0] ?? null;
        $location = $first['geometry']['location'] ?? null;

        if (!isset($location['lat'], $location['lng'])) {
            $status = $data['status'] ?? 'UNKNOWN';
            return ['lat' => null, 'lng' => null, 'warning' => "Google no devolvio coordenadas ({$status}) para {$address}"];
        }

        if ($strict) {
            $partial = (bool) ($first['partial_match'] ?? false);
            $types = $first['types'] ?? [];
            $allowed = ['street_address', 'premise', 'subpremise'];
            $hasAllowed = !empty(array_intersect($allowed, $types));
            if ($partial || !$hasAllowed) {
                return ['lat' => null, 'lng' => null, 'warning' => "Google no encontro direccion exacta para {$address}"];
            }
        }

        return ['lat' => (float) $location['lat'], 'lng' => (float) $location['lng']];
    }

    private function sanitizeAddress(string $address): string
    {
        // Remove phone numbers in parentheses and trim extra spaces
        $clean = preg_replace('/\s*\([^)]*\)/', '', $address) ?? $address;
        $clean = trim(preg_replace('/\s+/', ' ', $clean));

        return $this->ensureCommaBeforeLocality($clean);
    }

    private function ensureCommaBeforeLocality(string $address): string
    {
        if ($address === '' || str_contains($address, ',')) {
            return $address;
        }

        // Detect pattern: "calle numero localidad" and insert a comma before the locality part.
        $pattern = '/^(.+?\d+[^\s,]*)(\s+)([A-Za-zÁÉÍÓÚÜÑáéíóúüñ][\w\s\\-\\.]+)$/u';
        if (preg_match($pattern, $address, $matches)) {
            $street = trim($matches[1]);
            $locality = trim($matches[3]);
            if ($street !== '' && $locality !== '') {
                return $street . ', ' . $locality;
            }
        }

        return $address;
    }

    private function pickBestFeature(array $features): ?array
    {
        if (empty($features)) {
            return null;
        }

        foreach ($features as $feature) {
            $types = $feature['place_type'] ?? [];
            if (in_array('address', $types, true)) {
                return $feature;
            }
        }

        return $features[0];
    }
}
