<?php

namespace App\Http\Controllers;

use App\Models\Party;
use App\Models\TrafficLooseStopImportFormat;
use Illuminate\Http\Request;

class TrafficLooseStopImportFormatController extends Controller
{
    public function index()
    {
        $clients = Party::where('role', 'customer')
            ->orderBy('business_name')
            ->orderBy('name')
            ->get();

        $formats = TrafficLooseStopImportFormat::with('party')->get();
        $formatsMap = $formats->mapWithKeys(function (TrafficLooseStopImportFormat $format) {
            return [
                $format->party_id => [
                    'id' => $format->id,
                    'name' => $format->name,
                    'sheet' => $format->sheet,
                    'start_row' => $format->start_row,
                    'field_mappings' => $format->field_mappings ?? [],
                    'order_date_cell_column' => $format->order_date_cell_column,
                    'order_date_cell_row' => $format->order_date_cell_row,
                    'address_includes_locality' => $format->address_includes_locality,
                    'delete_route' => route('traffic.loose.import-config.destroy', $format),
                ],
            ];
        });

        return view('traffic.loose.import-formats.index', [
            'clients' => $clients,
            'formats' => $formats,
            'formatsMap' => $formatsMap,
        ]);
    }

    public function store(Request $request)
    {
        $fieldLabels = TrafficLooseStopImportFormat::fieldLabels();

        $rules = [
            'party_id' => 'required|exists:parties,id',
            'name' => 'nullable|string|max:120',
            'sheet' => 'nullable|string|max:120',
            'start_row' => 'nullable|integer|min:1',
        ];

        foreach ($fieldLabels as $name => $label) {
            $rules["fields.{$name}.column"] = 'nullable|string|max:5';
            $rules["fields.{$name}.row"] = 'nullable|integer|min:1';
        }
        $rules['address_includes_locality'] = 'nullable|boolean';
        $rules['order_date_cell_column'] = 'nullable|string|max:5';
        $rules['order_date_cell_row'] = 'nullable|integer|min:1';

        $validated = $request->validate($rules);

        $fieldMappings = [];
        $providedFields = $validated['fields'] ?? [];
        foreach ($fieldLabels as $fieldName => $label) {
            $item = $providedFields[$fieldName] ?? [];
            $column = isset($item['column']) ? trim(strtoupper((string) $item['column'])) : null;
            $row = isset($item['row']) ? (int) $item['row'] : null;

            if ($column === '') {
                $column = null;
            }

            if ($column === null && $row === null) {
                continue;
            }

            $fieldMappings[$fieldName] = array_filter([
                'column' => $column,
                'row' => $row,
            ], fn ($value) => $value !== null && $value !== '');
        }

        if (!isset($fieldMappings['address']) || !isset($fieldMappings['address']['column'])) {
            return back()->withInput()->with('info', 'Debes indicar al menos la columna donde viene la dirección.');
        }

        $startRow = $validated['start_row'] ?? 5;

        $format = TrafficLooseStopImportFormat::firstOrNew(['party_id' => $validated['party_id']]);
        $format->fill([
            'name' => $validated['name'] ?? null,
            'sheet' => $validated['sheet'] ?? null,
            'start_row' => $startRow,
            'field_mappings' => $fieldMappings,
            'order_date_cell_column' => $validated['order_date_cell_column'] ?? null,
            'order_date_cell_row' => $validated['order_date_cell_row'] ?? null,
            'address_includes_locality' => $request->boolean('address_includes_locality', false),
        ]);
        $format->save();

        return redirect()->route('traffic.loose.import-config')->with('ok', 'Configuración guardada.');
    }

    public function destroy(TrafficLooseStopImportFormat $format)
    {
        $format->delete();

        return redirect()
            ->route('traffic.loose.import-config')
            ->with('ok', 'Formato eliminado.');
    }
}
