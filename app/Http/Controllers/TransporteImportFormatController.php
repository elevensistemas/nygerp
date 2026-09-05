<?php

namespace App\Http\Controllers;

use App\Models\TransporteImportFormat;
use Illuminate\Http\Request;

class TransporteImportFormatController extends Controller
{
    public function index()
    {
        $format = TransporteImportFormat::first();
        $fieldLabels = TransporteImportFormat::fieldLabels();

        return view('traffic.transportes.import-config', [
            'format' => $format,
            'fieldLabels' => $fieldLabels,
        ]);
    }

    public function store(Request $request)
    {
        $fieldLabels = TransporteImportFormat::fieldLabels();
        $rules = [
            'name' => 'nullable|string|max:120',
            'sheet' => 'nullable|string|max:120',
            'start_row' => 'nullable|integer|min:1',
        ];

        foreach ($fieldLabels as $name => $label) {
            $rules["fields.{$name}.column"] = 'nullable|string|max:5';
        }

        $validated = $request->validate($rules);

        $fieldMappings = [];
        $provided = $validated['fields'] ?? [];
        foreach ($fieldLabels as $fieldName => $label) {
            $item = $provided[$fieldName] ?? [];
            $column = isset($item['column']) ? trim(strtoupper((string) $item['column'])) : null;
            if ($column === '') {
                $column = null;
            }
            if ($column === null) {
                continue;
            }
            $fieldMappings[$fieldName] = [
                'column' => $column,
            ];
        }

        $format = TransporteImportFormat::firstOrNew(['id' => 1]);
        $format->fill([
            'name' => $validated['name'] ?? null,
            'sheet' => $validated['sheet'] ?? null,
            'start_row' => $validated['start_row'] ?? 2,
            'field_mappings' => $fieldMappings,
        ]);
        $format->save();

        return redirect()->route('traffic.transportes.import-config')->with('ok', 'Configuración guardada.');
    }
}
