<?php

namespace App\Http\Controllers;

use App\Models\Transportista;
use App\Models\Transporte;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TransporteController extends Controller
{
    public function index(Request $request)
    {
        $editId = (int) $request->query('edit');
        return view('traffic.transportes.index', [
            'transportes'    => Transporte::with('transportista')->orderBy('alias')->paginate(15),
            'transportistas' => Transportista::orderBy('name')->get(),
            'editing'        => $editId ? Transporte::find($editId) : null,
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validatedData($request);
        
        // Si se marca como default, desmarcar otros transportes del mismo transportista
        if ($data['is_default'] ?? false) {
            Transporte::where('transportista_id', $data['transportista_id'])
                ->update(['is_default' => false]);
        }
        
        $transporte = Transporte::create($data);

        // Retornar JSON si es una petición AJAX
        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'message' => 'Transporte agregado.',
                'data' => $transporte,
            ], 201);
        }

        return redirect()
            ->route('traffic.transportes.index')
            ->with('ok', 'Transporte agregado.');
    }

    public function update(Request $request, Transporte $transporte)
    {
        $data = $this->validatedData($request);
        
        // Si se marca como default, desmarcar otros transportes del mismo transportista
        if ($data['is_default'] ?? false) {
            Transporte::where('transportista_id', $data['transportista_id'])
                ->where('id', '!=', $transporte->id)
                ->update(['is_default' => false]);
        }
        
        $transporte->update($data);

        // Retornar JSON si es una petición AJAX
        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'message' => 'Transporte actualizado.',
                'data' => $transporte,
            ], 200);
        }

        return redirect()
            ->route('traffic.transportes.index')
            ->with('ok', 'Transporte actualizado.');
    }

    public function edit(Transporte $transporte)
    {
        $transporte->load('transportista');
        
        return response()->json([
            'ok' => true,
            'data' => [
                'id'                   => $transporte->id,
                'transportista_id'     => $transporte->transportista_id,
                'transportista_name'   => $transporte->transportista ? $transporte->transportista->name : null,
                'driver_name'          => $transporte->driver_name,
                'owner_name'           => $transporte->owner_name,
                'status'               => $transporte->status,
                'alias'                => $transporte->alias,
                'license_plate'        => $transporte->license_plate,
                'type'                 => $transporte->type,
                'unit_color'           => $transporte->unit_color,
                'brand'                => $transporte->brand,
                'model'                => $transporte->model,
                'version'              => $transporte->version,
                'year'                 => $transporte->year,
                'chassis_number'       => $transporte->chassis_number,
                'fuel_type'            => $transporte->fuel_type,
                'registration_card'    => $transporte->registration_card,
                'vtv'                  => $transporte->vtv,
                'insurance'            => $transporte->insurance,
                'satellite'            => $transporte->satellite,
                'doors_count'          => $transporte->doors_count,
                'tank_capacity'        => $transporte->tank_capacity,
                'fuel_consumption_avg' => $transporte->fuel_consumption_avg,
                'capacity_kg'          => $transporte->capacity_kg,
                'length_cm'            => $transporte->length_cm,
                'width_cm'             => $transporte->width_cm,
                'height_cm'            => $transporte->height_cm,
                'volume_m3'            => $transporte->volume_m3,
                'tracking_identifier'  => $transporte->tracking_identifier,
                'notes'                => $transporte->notes,
                'is_active'            => $transporte->is_active,
                'is_default'           => $transporte->is_default,
            ]
        ]);
    }

    public function destroy(Transporte $transporte)
    {
        $transporte->delete();

        // Retornar JSON si es una petición AJAX
        if (request()->expectsJson()) {
            return response()->json([
                'ok' => true,
                'message' => 'Transporte eliminado.',
            ], 200);
        }

        return redirect()
            ->route('traffic.transportes.index')
            ->with('ok', 'Transporte eliminado.');
    }

    public function listByCarrier(Transportista $transportista)
    {
        $transportes = $transportista->transportes()
            ->where('is_active', true)
            ->orderBy('alias')
            ->get([
                'id',
                'alias',
                'license_plate',
                'type',
                'capacity_kg',
                'length_cm',
                'width_cm',
                'height_cm',
                'volume_m3',
                'is_default',
                'owner_name',
                'year',
            ])
            ->map(function ($transporte) {
                return [
                    'id'            => $transporte->id,
                    'alias'         => $transporte->alias,
                    'license_plate' => $transporte->license_plate,
                    'type'          => $transporte->type,
                    'capacity_kg'   => $transporte->capacity_kg,
                    'length_cm'     => $transporte->length_cm,
                    'width_cm'      => $transporte->width_cm,
                    'height_cm'     => $transporte->height_cm,
                    'volume_m3'     => $transporte->volume_m3,
                    'is_default'    => $transporte->is_default,
                    'owner_name'    => $transporte->owner_name,
                    'year'          => $transporte->year,
                ];
            });

        return response()->json([
            'data' => $transportes,
        ]);
    }

    public function setDefault(Transporte $transporte)
    {
        // Desmarcar otros transportes del mismo transportista
        Transporte::where('transportista_id', $transporte->transportista_id)
            ->where('id', '!=', $transporte->id)
            ->update(['is_default' => false]);
        
        // Marcar este como default
        $transporte->update(['is_default' => true]);

        return response()->json([
            'ok' => true,
            'message' => 'Transporte marcado como por defecto.',
        ]);
    }

    private function validatedData(Request $request): array
    {
        $data = $request->validate([
            'transportista_id'    => ['required', 'exists:transportistas,id'],
            'driver_name'         => ['nullable', 'string', 'max:255'],
            'owner_name'          => ['nullable', 'string', 'max:255'],
            'status'              => ['nullable', 'string', 'max:50'],
            'alias'               => ['required', 'string', 'max:100'],
            'license_plate'       => ['nullable', 'string', 'max:25'],
            'type'                => ['nullable', Rule::in(array_keys(Transporte::paymentVehicleTypes()))],
            'unit_color'          => ['nullable', 'string', 'max:80'],
            'brand'               => ['nullable', 'string', 'max:100'],
            'model'               => ['nullable', 'string', 'max:100'],
            'version'             => ['nullable', 'string', 'max:120'],
            'year'                => ['nullable', 'integer', 'min:1950', 'max:' . ((int) date('Y') + 1)],
            'chassis_number'      => ['nullable', 'string', 'max:120'],
            'fuel_type'           => ['nullable', 'string', 'max:80'],
            'registration_card'   => ['nullable', 'string', 'max:120'],
            'vtv'                 => ['nullable', 'string', 'max:120'],
            'insurance'           => ['nullable', 'string', 'max:120'],
            'satellite'           => ['nullable', 'string', 'max:255'],
            'doors_count'         => ['nullable', 'integer', 'min:0', 'max:20'],
            'tank_capacity'       => ['nullable', 'string', 'max:60'],
            'fuel_consumption_avg'=> ['nullable', 'string', 'max:80'],
            'capacity_kg'         => ['nullable', 'integer', 'min:0'],
            'length_cm'           => ['nullable', 'numeric', 'min:0'],
            'width_cm'            => ['nullable', 'numeric', 'min:0'],
            'height_cm'           => ['nullable', 'numeric', 'min:0'],
            'volume_m3'           => ['nullable', 'numeric', 'min:0'],
            'tracking_identifier' => ['nullable', 'string', 'max:120'],
            'is_active'           => ['sometimes', 'boolean'],
            'is_default'          => ['sometimes', 'boolean'],
            'notes'               => ['nullable', 'string'],
        ]);

        $data['is_active'] = $request->boolean('is_active', true);
        $data['is_default'] = $request->boolean('is_default', false);

        return $data;
    }
}
