<?php

namespace App\Http\Controllers;

use App\Models\TrafficZone;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TrafficZoneController extends Controller
{
    public function index()
    {
        $zones = TrafficZone::orderBy('name')->paginate(15)->withQueryString();

        return view('traffic.zones.index', [
            'zones' => $zones,
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validatedZone($request);
        $zone = TrafficZone::create($data);
        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'data' => $zone,
            ], 201);
        }

        return redirect()->route('traffic.zones.index')->with('ok', 'Zona creada.');
    }

    public function update(Request $request, TrafficZone $zone)
    {
        $data = $this->validatedZone($request, $zone);
        $zone->update($data);

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'data' => $zone->fresh(),
            ]);
        }

        return redirect()->route('traffic.zones.index')->with('ok', 'Zona actualizada.');
    }

    public function destroy(TrafficZone $zone)
    {
        $zone->delete();

        return redirect()->route('traffic.zones.index')->with('ok', 'Zona eliminada.');
    }

    private function validatedZone(Request $request, ?TrafficZone $zone = null): array
    {
        $uniqueName = Rule::unique('traffic_zones', 'name');
        if ($zone) {
            $uniqueName = $uniqueName->ignore($zone->id);
        }

        $rules = [
            'name' => ['required', 'string', 'max:255', $uniqueName],
            'type' => ['required', Rule::in(['circle', 'polygon'])],
            'center_lat' => ['nullable', 'numeric', 'between:-90,90'],
            'center_lng' => ['nullable', 'numeric', 'between:-180,180'],
            'radius_km' => ['nullable', 'numeric', 'min:0'],
            'polygon' => ['nullable'],
            'priority' => ['required', Rule::in(['primary', 'secondary'])],
            'is_soft' => ['sometimes', 'boolean'],
            'max_stops' => ['nullable', 'integer', 'min:1'],
            'svs_values' => ['nullable', 'string'],
        ];

        $data = $request->validate($rules);
        $data['is_soft'] = $request->boolean('is_soft', false);

        if (isset($data['svs_values'])) {
            $lines = explode("\n", str_replace("\r", "", $data['svs_values']));
            $parsed = array_filter(array_map('trim', $lines), function($val) {
                return $val !== '';
            });
            $data['svs_values'] = array_values($parsed);
        } else {
            $data['svs_values'] = [];
        }

        if ($data['type'] === 'circle') {
            if (! $data['is_soft'] && !isset($data['center_lat'], $data['center_lng'], $data['radius_km'])) {
                abort(422, 'Centro y radio requeridos para zona circular.');
            }
            $data['polygon'] = null;
        } else {
            $polygonRaw = $request->input('polygon');
            if (is_string($polygonRaw)) {
                $decoded = json_decode($polygonRaw, true);
                $polygonRaw = $decoded;
            }
            if (!is_array($polygonRaw) || count($polygonRaw) < 3) {
                abort(422, 'Polígono inválido: se requieren al menos 3 puntos.');
            }
            $data['polygon'] = $polygonRaw;
            $data['center_lat'] = null;
            $data['center_lng'] = null;
            $data['radius_km'] = null;
        }

        return $data;
    }

    public function updateSvs(Request $request, TrafficZone $zone)
    {
        $data = $request->validate([
            'svs_values' => ['nullable', 'string'],
        ]);

        if (isset($data['svs_values'])) {
            $lines = explode("\n", str_replace("\r", "", $data['svs_values']));
            $parsed = array_filter(array_map('trim', $lines), function($val) {
                return $val !== '';
            });
            $parsedSvs = array_values($parsed);
        } else {
            $parsedSvs = [];
        }

        $zone->update([
            'svs_values' => $parsedSvs
        ]);

        return response()->json([
            'ok' => true,
            'svs_values' => $parsedSvs,
        ]);
    }
}
