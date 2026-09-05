<?php

namespace App\Http\Controllers;

use App\Models\Party;
use App\Models\TrafficLooseStop;
use App\Models\TrafficLooseStopImportFormat;
use App\Services\Geocoder;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class TrafficLooseStopController extends Controller
{
    public function index(Request $request)
    {
        $this->guardTransportista($request);

        $clients = Party::where('role', 'customer')
            ->orderBy('business_name')
            ->orderBy('name')
            ->get();

        $clusterBy = $request->query('cluster_by', 'address');
        $clusterBy = in_array($clusterBy, ['address', 'priority'], true) ? $clusterBy : 'address';

        $clientFilter = array_filter((array) $request->query('clients', []), fn ($id) => (int) $id > 0);
        $orderFrom = $request->query('order_from');
        $orderTo = $request->query('order_to');
        $priorityFilter = $request->query('priority');
        $codeFilter = $request->query('code');

        $stopsQuery = TrafficLooseStop::pending()
            ->whereNull('assigned_route_id')
            ->with('party')
            ->orderByDesc('id');

        if (!empty($clientFilter)) {
            $stopsQuery->whereIn('party_id', $clientFilter);
        }
        if ($orderFrom) {
            $stopsQuery->whereDate('order_date', '>=', $orderFrom);
        }
        if ($orderTo) {
            $stopsQuery->whereDate('order_date', '<=', $orderTo);
        }
        if ($priorityFilter) {
            $stopsQuery->where('priority', $priorityFilter);
        }
        if ($codeFilter) {
            $stopsQuery->where(function ($query) use ($codeFilter) {
                $query->where('code', 'like', '%' . $codeFilter . '%')
                    ->orWhere('tracking_number', 'like', '%' . $codeFilter . '%')
                    ->orWhere('barcode', 'like', '%' . $codeFilter . '%')
                    ->orWhere('qr_code', 'like', '%' . $codeFilter . '%');
            });
        }

        $stops = $stopsQuery
            ->take(800)
            ->get();

        $clientColors = $this->mapClientColors($stops->pluck('party')->filter()->unique('id'));

        $stopsForMap = $stops->map(function ($stop) use ($clientColors) {
            $party = $stop->party;
            $clientName = $party ? ($party->business_name ?: $party->name) : 'Sin cliente';

            return [
                'id' => $stop->id,
                'code' => $stop->code,
                'address' => $stop->address,
                'order_date' => optional($stop->order_date)->format('d/m/Y'),
                'lat' => $stop->latitude,
                'lng' => $stop->longitude,
                'priority' => $stop->priority,
                'notes' => $stop->notes,
                'sender_name' => $stop->sender_name,
                'sender_address' => $stop->sender_address,
                'sender_contact' => $stop->sender_contact,
                'recipient_name' => $stop->recipient_name,
                'recipient_address' => $stop->recipient_address,
                'recipient_contact' => $stop->recipient_contact,
                'tracking_number' => $stop->tracking_number,
                'length' => $stop->length,
                'width' => $stop->width,
                'height' => $stop->height,
                'weight_actual' => $stop->weight_actual,
                'weight_volumetric' => $stop->weight_volumetric,
                'content_description' => $stop->content_description,
                'declared_value' => $stop->declared_value,
                'barcode' => $stop->barcode,
                'qr_code' => $stop->qr_code,
                'source_filename' => $stop->source_filename,
                'client_name' => $clientName,
                'party_id' => $stop->party_id,
                'color' => $clientColors[$stop->party_id] ?? '#2563eb',
            ];
        })->filter(function ($stop) {
            return is_numeric($stop['lat']) && is_numeric($stop['lng']);
        })->values();

        $invalidUploaded = 0;

        $mapProvider = session('loose_map_provider', 'openstreet');

        return view('traffic.loose.index', [
            'clients' => $clients,
            'filters' => [
                'clients' => $clientFilter,
                'order_from' => $orderFrom,
                'order_to' => $orderTo,
                'priority' => $priorityFilter,
                'code' => $codeFilter,
            ],
            'clusterBy' => $clusterBy,
            'stops' => $stops,
            'stopsForMap' => $stopsForMap,
            'clientColors' => $clientColors,
            'warnings' => session('warnings', []),
            'uploadedStops' => collect(),
            'mapProvider' => $mapProvider,
            'selectedCustomer' => null,
            'invalidUploaded' => $invalidUploaded,
            'importFormats' => $this->loadImportFormats(),
        ]);
    }

    public function store(Request $request, Geocoder $geocoder)
    {
        $this->guardTransportista($request);
        set_time_limit(0);

        $data = $request->validate([
            'party_id' => ['required', 'integer', 'exists:parties,id'],
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv,txt'],
            'sheet' => ['nullable', 'string'],
            'map_provider' => ['nullable', 'string', 'in:openstreet,mapbox,google'],
        ]);

        $client = Party::find($data['party_id']);
        $format = $client ? $client->looseStopImportFormat : null;
        $stops = $this->parseUploadedStops($request, $data['sheet'] ?? null, $format);
        if ($stops->isEmpty()) {
            return back()->withInput()->with('info', 'No se encontraron direcciones en el archivo.');
        }

        // Usamos el proveedor seleccionado
        $provider = $data['map_provider'] ?? 'mapbox';
        session(['loose_map_provider' => $provider]);
        [$geocodedStops, $warnings] = $geocoder->geocodeStops($stops, $provider, true, true);

        $count = $this->persistLooseStops(
            $geocodedStops,
            (int) $data['party_id'],
            $request->user(),
            $request->file('file')
        );

        return redirect()
            ->route('traffic.loose.index')
            ->with('ok', "Se guardaron {$count} direcciones sueltas.")
            ->with('warnings', $warnings);
    }

    public function template(Request $request)
    {
        $this->guardTransportista($request);

        $headers = array_values(TrafficLooseStopImportFormat::fieldLabels());

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray($headers, null, 'A1');
        $sheet->freezePane('A2');
        $highestColumn = $sheet->getHighestColumn();
        $sheet->getStyle("A1:{$highestColumn}1")->getFont()->setBold(true);
        $sheet->setTitle('Plantilla direcciones');

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, 'modelo-direcciones-sueltas.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function destroy(Request $request, TrafficLooseStop $stop)
    {
        $this->guardTransportista($request);
        $stop->delete();

        return redirect()
            ->route('traffic.loose.index')
            ->with('ok', 'Dirección eliminada.');
    }

    public function bulkDestroy(Request $request)
    {
        $this->guardTransportista($request);
        $ids = collect($request->input('ids', []))
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->values();

        if ($ids->isEmpty()) {
            return back()->with('info', 'Selecciona al menos una direccion para eliminar.');
        }

        $deleted = TrafficLooseStop::pending()
            ->whereNull('assigned_route_id')
            ->whereIn('id', $ids)
            ->delete();

        return redirect()
            ->route('traffic.loose.index')
            ->with('ok', "Se eliminaron {$deleted} direcciones sueltas.");
    }

    
public function preview(Request $request, Geocoder $geocoder)
    {
        $this->guardTransportista($request);
        set_time_limit(0);

        if ($request->isMethod('get')) {
            return redirect()->route('traffic.loose.index');
        }

        $data = $request->validate([
            'file' => 'nullable|file|mimes:xlsx,xls,csv,txt',
            'sheet' => 'nullable|string',
            'manual_addresses' => 'nullable|string',
            'import_action' => 'nullable|string|in:validate,save',
            'map_provider' => 'required|string|in:openstreet,mapbox,google',
            'party_id' => ['required', 'integer', 'exists:parties,id'],
        ]);

        
        // Usamos el proveedor seleccionado
        session(['loose_map_provider' => $data['map_provider']]);

        $clients = Party::where('role', 'customer')
            ->orderBy('business_name')
            ->orderBy('name')
            ->get();

        $clientFilter = array_filter((array) $request->query('clients', []), fn ($id) => (int) $id > 0);
        $orderFrom = $request->query('order_from');
        $orderTo = $request->query('order_to');
        $priorityFilter = $request->query('priority');
        $codeFilter = $request->query('code');

        $stopsQuery = TrafficLooseStop::pending()
            ->whereNull('assigned_route_id')
            ->with('party')
            ->orderByDesc('id');

        if (!empty($clientFilter)) {
            $stopsQuery->whereIn('party_id', $clientFilter);
        }
        if ($orderFrom) {
            $stopsQuery->whereDate('order_date', '>=', $orderFrom);
        }
        if ($orderTo) {
            $stopsQuery->whereDate('order_date', '<=', $orderTo);
        }
        if ($priorityFilter) {
            $stopsQuery->where('priority', $priorityFilter);
        }
        if ($codeFilter) {
            $stopsQuery->where(function ($query) use ($codeFilter) {
                $query->where('code', 'like', '%' . $codeFilter . '%')
                    ->orWhere('tracking_number', 'like', '%' . $codeFilter . '%')
                    ->orWhere('barcode', 'like', '%' . $codeFilter . '%');
            });
        }

        $stops = $stopsQuery
            ->take(800)
            ->get();

        $clientColors = $this->mapClientColors($stops->pluck('party')->filter()->unique('id'));
        $stopsForMap = $stops->map(function ($stop) use ($clientColors) {
            $party = $stop->party;
            $clientName = $party ? ($party->business_name ?: $party->name) : 'Sin cliente';

            return [
                'id' => $stop->id,
                'code' => $stop->code,
                'address' => $stop->address,
                'order_date' => optional($stop->order_date)->format('d/m/Y'),
                'lat' => $stop->latitude,
                'lng' => $stop->longitude,
                'priority' => $stop->priority,
                'notes' => $stop->notes,
                'sender_name' => $stop->sender_name,
                'sender_address' => $stop->sender_address,
                'sender_contact' => $stop->sender_contact,
                'recipient_name' => $stop->recipient_name,
                'recipient_address' => $stop->recipient_address,
                'recipient_contact' => $stop->recipient_contact,
                'tracking_number' => $stop->tracking_number,
                'length' => $stop->length,
                'width' => $stop->width,
                'height' => $stop->height,
                'weight_actual' => $stop->weight_actual,
                'weight_volumetric' => $stop->weight_volumetric,
                'content_description' => $stop->content_description,
                'declared_value' => $stop->declared_value,
                'barcode' => $stop->barcode,
                'source_filename' => $stop->source_filename,
                'client_name' => $clientName,
                'party_id' => $stop->party_id,
                'color' => $clientColors[$stop->party_id] ?? '#2563eb',
            ];
        })->filter(function ($stop) {
            return is_numeric($stop['lat']) && is_numeric($stop['lng']);
        })->values();

        $manualPayload = $request->input('manual_addresses');
        $hasManual = !empty($manualPayload);
        $hasFile = $request->hasFile('file');

        if (!$hasFile && !$hasManual) {
            return back()->withInput()->with('info', 'Carga un archivo o corrige las direcciones para continuar.');
        }

        $extension = $hasFile
            ? strtolower((string) $request->file('file')->getClientOriginalExtension())
            : null;
        $canUseSpreadsheet = class_exists(IOFactory::class);
        if ($hasFile && in_array($extension, ['xlsx', 'xls'], true) && !$canUseSpreadsheet) {
            return back()->withInput()->with('info', 'No se pudo leer el archivo Excel.');
        }

        $client = Party::find($data['party_id']);
        $format = $client ? $client->looseStopImportFormat : null;

        if ($hasManual) {
            $decoded = json_decode($manualPayload, true);
            if (!is_array($decoded)) {
                return back()->withInput()->with('info', 'No se pudieron leer las direcciones corregidas.');
            }
            $uploadedStops = collect($decoded)->map(function ($row, $index) {
                $row = is_array($row) ? $row : [];
                $row['code'] = $row['code'] ?? ('PED-' . str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT));
                $row['address'] = trim((string) ($row['address'] ?? ''));
                $row['priority'] = $row['priority'] ?? 'Media';
                $row['lat'] = $row['lat'] ?? null;
                $row['lng'] = $row['lng'] ?? null;
                $row['notes'] = $row['notes'] ?? '';
                $row['loose_stop_id'] = $row['loose_stop_id'] ?? null;
                return $row;
            })->filter(fn ($row) => !empty($row['address']))->values();
        } else {
            $uploadedStops = $this->parseUploadedStops($request, $data['sheet'] ?? null, $format);
        }

        if ($uploadedStops->isEmpty()) {
            return back()->withInput()->with('info', 'No se encontraron direcciones en el archivo.');
        }

        [$geocodedStops, $geoWarnings] = $geocoder->geocodeStops($uploadedStops, $data['map_provider'], true, true);
        $invalidStops = $geocodedStops->filter(function ($stop) {
            $lat = $stop['lat'] ?? null;
            $lng = $stop['lng'] ?? null;
            return !is_numeric($lat) || !is_numeric($lng);
        });
        $invalidUploaded = $invalidStops->count();
        $action = $data['import_action'] ?? 'validate';

        if ($invalidStops->isNotEmpty()) {
            $geoWarnings[] = 'Hay direcciones invalidas. Corrigelas para continuar.';
        }

        if ($action !== 'save' || $invalidStops->isNotEmpty()) {
        return view('traffic.loose.index', [
            'clients' => $clients,
            'filters' => [
                'clients' => $clientFilter,
                'order_from' => $orderFrom,
                'order_to' => $orderTo,
                'priority' => $priorityFilter,
                'code' => $codeFilter,
            ],
            'stops' => $stops,
            'stopsForMap' => $stopsForMap,
            'clientColors' => $clientColors,
            'warnings' => $geoWarnings,
            'uploadedStops' => $geocodedStops,
            'mapProvider' => $data['map_provider'],
            'selectedCustomer' => $data['party_id'],
            'invalidUploaded' => $invalidUploaded,
            'importFormats' => $this->loadImportFormats(),
        ]);
        }

        $count = $this->persistLooseStops($geocodedStops, (int) $data['party_id'], $request->user(), $request->file('file'));

        $message = "Se guardaron {$count} direcciones sueltas.";
        if ($geoWarnings) {
            $message .= ' Advertencias: ' . implode(' ', $geoWarnings);
        }

        return redirect()->route('traffic.loose.index')->with('ok', $message);
    }

    private function guardTransportista(Request $request): void
    {
        $user = $request->user();
        if ($user && $user->isTransportista() && ! $user->isAdminOrSuper()) {
            abort(403, 'Acceso no disponible para transportistas.');
        }
    }

    private function mapClientColors(Collection $clients): array
    {
        $palette = [
            '#2563eb',
            '#16a34a',
            '#f97316',
            '#7c3aed',
            '#0ea5e9',
            '#f59e0b',
            '#dc2626',
            '#14b8a6',
            '#8b5cf6',
            '#65a30d',
        ];

        $colors = [];
        $index = 0;
        foreach ($clients as $client) {
            $colors[$client->id] = $palette[$index % count($palette)];
            $index++;
        }

        return $colors;
    }

    private function parseUploadedStops(Request $request, ?string $sheet = null, ?TrafficLooseStopImportFormat $format = null): Collection
    {
        if ($format && is_array($format->field_mappings) && count($format->field_mappings)) {
            return $this->parseConfiguredUploadedStops($request, $sheet, $format);
        }

        return $this->legacyParseUploadedStops($request, $sheet);
    }

    private function legacyParseUploadedStops(Request $request, ?string $sheet = null): Collection
    {
        $file = $request->file('file');
        $rows = [];
        $extension = strtolower($file->getClientOriginalExtension());
        $canUseSpreadsheet = class_exists(IOFactory::class);

        if ($canUseSpreadsheet && in_array($extension, ['xlsx', 'xls'], true)) {
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

    private function parseConfiguredUploadedStops(Request $request, ?string $sheet, TrafficLooseStopImportFormat $format): Collection
    {
        $file = $request->file('file');
        $rows = [];
        $extension = strtolower($file->getClientOriginalExtension());
        $canUseSpreadsheet = class_exists(IOFactory::class);
        $startRow = max((int) ($format->start_row ?? 1), 1);

        if ($canUseSpreadsheet && in_array($extension, ['xlsx', 'xls'], true)) {
            $spreadsheet = IOFactory::load($file->getRealPath());
            $worksheet = $this->selectWorksheet($spreadsheet, $sheet, $format);
            $rows = $worksheet->toArray(null, true, true, true);
        } else {
            if (($handle = fopen($file->getRealPath(), 'r')) !== false) {
                $rowIndex = 0;
                while (($row = fgetcsv($handle, 0, ',')) !== false) {
                    $rowIndex++;
                    $rows[$rowIndex] = array_map('trim', $row);
                }
                fclose($handle);
            }
        }

        $stops = collect();
        $globalOrderDateValue = null;
        $orderDateColumn = $format->order_date_cell_column ? strtoupper(trim($format->order_date_cell_column)) : null;
        $orderDateRow = $format->order_date_cell_row;
        if ($orderDateColumn && $orderDateRow) {
            $globalOrderDateValue = trim((string) ($this->readCellValue($rows, $orderDateColumn, $orderDateRow) ?? ''));
        }

        foreach ($rows as $rowNumber => $row) {
            $rowNumber = (int) $rowNumber;
            if ($rowNumber < $startRow) {
                continue;
            }

            $addressBase = trim((string) ($this->readConfiguredCell($row, $format, 'address') ?? ''));
            $localityValue = trim((string) ($this->readConfiguredCell($row, $format, 'locality') ?? ''));
            if ($addressBase === '') {
                continue;
            }

            $includeLocality = (bool) $format->address_includes_locality;
            $fullAddress = $addressBase;
            if (! $includeLocality && $localityValue !== '') {
                $hasLocality = stripos($addressBase, $localityValue) !== false;
                if (! $hasLocality) {
                    $fullAddress = $addressBase . ', ' . $localityValue;
                }
            }

            $code = trim((string) ($this->readConfiguredCell($row, $format, 'code') ?? ''));
            $notes = trim((string) ($this->readConfiguredCell($row, $format, 'notes') ?? ''));
            $priority = trim((string) ($this->readConfiguredCell($row, $format, 'priority') ?? ''));
            $latitude = $this->normalizeDecimalValue($this->readConfiguredCell($row, $format, 'latitude'));
            $longitude = $this->normalizeDecimalValue($this->readConfiguredCell($row, $format, 'longitude'));
            $senderName = trim((string) ($this->readConfiguredCell($row, $format, 'sender_name') ?? ''));
            $senderAddress = trim((string) ($this->readConfiguredCell($row, $format, 'sender_address') ?? ''));
            $senderContact = trim((string) ($this->readConfiguredCell($row, $format, 'sender_contact') ?? ''));
            $recipientName = trim((string) ($this->readConfiguredCell($row, $format, 'recipient_name') ?? ''));
            $recipientAddress = trim((string) ($this->readConfiguredCell($row, $format, 'recipient_address') ?? ''));
            $recipientContact = trim((string) ($this->readConfiguredCell($row, $format, 'recipient_contact') ?? ''));
            $trackingNumber = trim((string) ($this->readConfiguredCell($row, $format, 'tracking_number') ?? ''));
            $length = $this->normalizeDecimalValue($this->readConfiguredCell($row, $format, 'length'));
            $width = $this->normalizeDecimalValue($this->readConfiguredCell($row, $format, 'width'));
            $height = $this->normalizeDecimalValue($this->readConfiguredCell($row, $format, 'height'));
            $weightActual = $this->normalizeDecimalValue($this->readConfiguredCell($row, $format, 'weight_actual'));
            $weightVolumetric = $this->normalizeDecimalValue($this->readConfiguredCell($row, $format, 'weight_volumetric'));
            $contentDescription = trim((string) ($this->readConfiguredCell($row, $format, 'content_description') ?? ''));
            $declaredValue = $this->normalizeDecimalValue($this->readConfiguredCell($row, $format, 'declared_value'));
            $barcode = trim((string) ($this->readConfiguredCell($row, $format, 'barcode') ?? ''));
            $qrCode = trim((string) ($this->readConfiguredCell($row, $format, 'qr_code') ?? ''));
            $orderDateText = trim((string) ($this->readConfiguredCell($row, $format, 'order_date') ?? ''));
            if ($orderDateText === '' && $globalOrderDateValue !== null) {
                $orderDateText = $globalOrderDateValue;
            }
            $orderDate = $this->parseOrderDateValue($orderDateText);

            $stops->push([
                'code' => $code ?: 'PED-' . str_pad((string) ($stops->count() + 1), 3, '0', STR_PAD_LEFT),
                'address' => $fullAddress,
                'priority' => $priority ?: 'Media',
                'lat' => $latitude,
                'lng' => $longitude,
                'notes' => $notes,
                'order_date' => $orderDate,
                'sender_name' => $senderName ?: null,
                'sender_address' => $senderAddress ?: null,
                'sender_contact' => $senderContact ?: null,
                'recipient_name' => $recipientName ?: null,
                'recipient_address' => $recipientAddress ?: null,
                'recipient_contact' => $recipientContact ?: null,
                'tracking_number' => $trackingNumber ?: null,
                'length' => $length,
                'width' => $width,
                'height' => $height,
                'weight_actual' => $weightActual,
                'weight_volumetric' => $weightVolumetric,
                'content_description' => $contentDescription ?: null,
                'declared_value' => $declaredValue,
                'barcode' => $barcode ?: null,
                'qr_code' => $qrCode ?: null,
            ]);
        }

       return $stops;
    }

    private function loadImportFormats(): array
    {
        $formats = TrafficLooseStopImportFormat::with('party')->get();
        return $formats->mapWithKeys(function (TrafficLooseStopImportFormat $format) {
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
                    'display_name' => $format->name ?: 'Formato ' . $format->id,
                ],
            ];
        })->all();
    }

    private function selectWorksheet(Spreadsheet $spreadsheet, ?string $sheetValue, TrafficLooseStopImportFormat $format): Worksheet
    {
        $candidate = trim((string) ($sheetValue ?? $format->sheet ?? ''));
        if ($candidate !== '') {
            if (is_numeric($candidate)) {
                $index = max(0, (int) $candidate);
                if ($index < $spreadsheet->getSheetCount()) {
                    return $spreadsheet->getSheet($index);
                }
            } else {
                $worksheet = $spreadsheet->getSheetByName($candidate);
                if ($worksheet) {
                    return $worksheet;
                }
            }
        }

        return $spreadsheet->getActiveSheet();
    }

    private function readConfiguredCell(array $row, TrafficLooseStopImportFormat $format, string $field): ?string
    {
        $column = $format->columnFor($field);
        if (! $column) {
            return null;
        }

        if (array_key_exists($column, $row)) {
            return $row[$column];
        }

        $values = array_values($row);
        $index = $this->columnLetterToIndex($column);

        return $values[$index] ?? null;
    }

    private function readCellValue(array $rows, string $column, int $rowNumber): ?string
    {
        $row = $rows[$rowNumber] ?? null;
        if (!is_array($row)) {
            return null;
        }

        if (array_key_exists($column, $row)) {
            return $row[$column];
        }

        $values = array_values($row);
        $index = $this->columnLetterToIndex($column);

        return $values[$index] ?? null;
    }

    private function parseOrderDateValue(?string $value): ?Carbon
    {
        if ($value === null) {
            return null;
        }
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function columnLetterToIndex(string $column): int
    {
        $clean = strtoupper(preg_replace('/[^A-Z]/', '', $column));
        $length = strlen($clean);
        $index = 0;

        for ($i = 0; $i < $length; $i++) {
            $index *= 26;
            $index += ord($clean[$i]) - ord('A') + 1;
        }

        return max(0, $index - 1);
    }

    private function normalizeDecimalValue($value): ?float
    {
        if ($value === null) {
            return null;
        }

        $clean = preg_replace('/[^\d,\.\-]/', '', (string) $value);
        $clean = str_replace(',', '.', $clean);
        $clean = trim($clean);

        if ($clean === '' || ! is_numeric($clean)) {
            return null;
        }

        return (float) $clean;
    }

    private function persistLooseStops(Collection $stops, int $partyId, $user = null, ?UploadedFile $file = null): int
    {
        $source = $file ? $file->getClientOriginalName() : null;
        $userId = $user ? $user->id : null;
        $saved = 0;

        foreach ($stops as $stop) {
            $address = trim((string) ($stop['address'] ?? ''));
            if ($address === '') {
                continue;
            }

            $fingerprint = TrafficLooseStop::fingerprint($partyId, $stop['code'] ?? null, $address);
            $payload = [
                'party_id' => $partyId,
                'uploaded_by' => $userId,
                'code' => $stop['code'] ?? null,
                'address' => $address,
                'order_date' => $stop['order_date'] ?? null,
                'sender_name' => $stop['sender_name'] ?? null,
                'sender_address' => $stop['sender_address'] ?? null,
                'sender_contact' => $stop['sender_contact'] ?? null,
                'recipient_name' => $stop['recipient_name'] ?? null,
                'recipient_address' => $stop['recipient_address'] ?? null,
                'recipient_contact' => $stop['recipient_contact'] ?? null,
                'tracking_number' => $stop['tracking_number'] ?? null,
                'length' => isset($stop['length']) ? (float) $stop['length'] : null,
                'width' => isset($stop['width']) ? (float) $stop['width'] : null,
                'height' => isset($stop['height']) ? (float) $stop['height'] : null,
                'weight_actual' => isset($stop['weight_actual']) ? (float) $stop['weight_actual'] : null,
                'weight_volumetric' => isset($stop['weight_volumetric']) ? (float) $stop['weight_volumetric'] : null,
                'content_description' => $stop['content_description'] ?? null,
                'declared_value' => isset($stop['declared_value']) ? (float) $stop['declared_value'] : null,
                'barcode' => $stop['barcode'] ?? null,
                'qr_code' => $stop['qr_code'] ?? null,
                'latitude' => isset($stop['lat']) ? (float) $stop['lat'] : (isset($stop['latitude']) ? (float) $stop['latitude'] : null),
                'longitude' => isset($stop['lng']) ? (float) $stop['lng'] : (isset($stop['longitude']) ? (float) $stop['longitude'] : null),
                'priority' => $stop['priority'] ?? null,
                'notes' => $stop['notes'] ?? null,
                'source_filename' => $source,
                'fingerprint' => $fingerprint,
            ];

            $existing = TrafficLooseStop::pending()
                ->where('fingerprint', $fingerprint)
                ->first();

            if ($existing) {
                $existing->fill($payload);
                $existing->save();
                $saved++;
                continue;
            }

            TrafficLooseStop::create($payload);
            $saved++;
        }

        return $saved;
    }
}
