<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use App\Models\TrafficZone;
use App\Models\Bank;
use App\Models\Transportista;
use App\Models\TransportistaZone;
use App\Models\Transporte;
use App\Models\TransportistaImportFormat;
use App\Models\TransporteImportFormat;
use App\Models\User;
use App\Mail\UserCreatedMail;
use App\Models\SystemParameter;
use App\Services\TermsAcceptanceService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Pagination\LengthAwarePaginator;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Carbon\Carbon;
use Illuminate\Validation\Rule;

class TransportistaController extends Controller
{
    public function index(Request $request)
    {
        $editId = (int) $request->query('edit');
        $query = Transportista::query()->with(['user', 'supplier', 'bank', 'defaultPaymentMethod.bank', 'liquidationMeta']);
        $filters = [
            'transportista_id' => $request->query('transportista_id'),
            'transporte_id' => $request->query('transporte_id'),
            'user_id' => $request->query('user_id'),
            'has_zones' => $request->query('has_zones'),
            'has_transportes' => $request->query('has_transportes'),
            'address' => $request->query('address'),
            'address_lat' => $request->query('address_lat'),
            'address_lng' => $request->query('address_lng'),
        ];

        if (!empty($filters['transportista_id'])) {
            $query->where('id', (int) $filters['transportista_id']);
        }
        if (!empty($filters['user_id'])) {
            if ($filters['user_id'] === 'none') {
                $query->whereNull('user_id');
            } else {
                $query->where('user_id', (int) $filters['user_id']);
            }
        }
        if (!empty($filters['transporte_id'])) {
            $query->whereHas('transportes', function ($sub) use ($filters) {
                $sub->where('id', (int) $filters['transporte_id']);
            });
        }
        if ($filters['has_zones'] === 'yes') {
            $query->where(function ($sub) {
                $sub->whereHas('zones')->orWhereHas('sharedZones');
            });
        } elseif ($filters['has_zones'] === 'no') {
            $query->where(function ($sub) {
                $sub->whereDoesntHave('zones')->whereDoesntHave('sharedZones');
            });
        }
        if ($filters['has_transportes'] === 'yes') {
            $query->whereHas('transportes');
        } elseif ($filters['has_transportes'] === 'no') {
            $query->whereDoesntHave('transportes');
        }

        $addressLat = $filters['address_lat'];
        $addressLng = $filters['address_lng'];
        if (($filters['address'] ?? '') !== '' && (empty($addressLat) || empty($addressLng))) {
            [$addressLat, $addressLng] = $this->geocodeAddress($filters['address']);
        }

        if ($addressLat && $addressLng) {
            $transportistasCollection = $query->with(['zones', 'sharedZones'])->orderBy('name')->get();
            $filtered = $transportistasCollection->filter(function (Transportista $transportista) use ($addressLat, $addressLng) {
                return $transportista->allZones()->contains(function ($zone) use ($addressLat, $addressLng) {
                    return $zone->containsPoint((float) $addressLat, (float) $addressLng);
                });
            })->values();

            $page = max((int) $request->query('page', 1), 1);
            $perPage = 15;
            $transportistas = new LengthAwarePaginator(
                $filtered->forPage($page, $perPage),
                $filtered->count(),
                $perPage,
                $page,
                [
                    'path' => $request->url(),
                    'query' => $request->query(),
                ]
            );
        } else {
            $transportistas = $query->orderBy('name')->paginate(15)->withQueryString();
        }

        $transportistaImportFormat = TransportistaImportFormat::first();
        $transporteImportFormat = TransporteImportFormat::first();
        $transportistaImportConfig = $transportistaImportFormat ? [
            'name' => $transportistaImportFormat->name,
            'sheet' => $transportistaImportFormat->sheet,
            'start_row' => $transportistaImportFormat->start_row,
            'field_mappings' => $transportistaImportFormat->field_mappings ?? [],
        ] : null;
        $transporteImportConfig = $transporteImportFormat ? [
            'name' => $transporteImportFormat->name,
            'sheet' => $transporteImportFormat->sheet,
            'start_row' => $transporteImportFormat->start_row,
            'field_mappings' => $transporteImportFormat->field_mappings ?? [],
        ] : null;

        return view('traffic.transportistas.index', [
            'transportistas' => $transportistas,
            'transportistasFilter' => Transportista::orderBy('name')->get(['id', 'name']),
            'transportesFilter' => Transporte::orderBy('alias')->get(['id', 'alias', 'license_plate', 'transportista_id']),
            'users' => $this->freeTransportistaUsers(),
            'suppliers' => Supplier::active()->orderBy('name')->get(),
            'editing' => $editId ? Transportista::with(['user', 'supplier', 'bank', 'liquidationMeta'])->find($editId) : null,
            'banks' => Bank::query()->orderBy('name')->get(['id', 'name']),
            'filters' => $filters,
        'addressLat' => $addressLat,
        'addressLng' => $addressLng,
        'transportistaImportConfig' => $transportistaImportConfig,
        'transporteImportConfig' => $transporteImportConfig,
        'transportistaImportFieldLabels' => TransportistaImportFormat::fieldLabels(),
        'transporteImportFieldLabels' => TransporteImportFormat::fieldLabels(),
    ]);
    }

    public function store(Request $request)
    {
        $data = $this->validatedData($request);
        $transportistaData = Arr::except($data, ['liquidation_tipo_periodo']);
        
        // Crear o actualizar el usuario asociado
        $user = $this->createOrUpdateUser($transportistaData, null);
        if ($user) {
            $transportistaData['user_id'] = $user->id;
        }
        
        $transportista = Transportista::create($transportistaData);
        $this->syncLiquidationMeta($transportista, $data);

        // Retornar JSON si es una petición AJAX
        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'message' => 'Transportista creado.',
                'data' => $transportista->load('liquidationMeta'),
            ], 201);
        }

        return redirect()->route('traffic.transportistas.index')->with('ok', 'Transportista creado.');
    }

    public function update(Request $request, Transportista $transportista)
    {
        $data = $this->validatedData($request, $transportista);
        $transportistaData = Arr::except($data, ['liquidation_tipo_periodo']);
        
        // Crear o actualizar el usuario asociado
        $user = $this->createOrUpdateUser($transportistaData, $transportista);
        if ($user) {
            $transportistaData['user_id'] = $user->id;
        }
        
        $transportista->update($transportistaData);
        $this->syncLiquidationMeta($transportista, $data);

        // Retornar JSON si es una petición AJAX
        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'message' => 'Transportista actualizado.',
                'data' => $transportista->load('liquidationMeta'),
            ], 200);
        }

        return redirect()->route('traffic.transportistas.index')->with('ok', 'Transportista actualizado.');
    }

    public function toggleActive(Transportista $transportista)
    {
        $transportista->update(['is_active' => !$transportista->is_active]);

        return response()->json([
            'ok' => true,
            'message' => $transportista->is_active ? 'Transportista activado.' : 'Transportista desactivado.',
            'is_active' => $transportista->is_active,
        ]);
    }

    public function destroy(Transportista $transportista)
    {
        $transportista->delete();

        // Retornar JSON si es una petición AJAX
        if (request()->expectsJson()) {
            return response()->json([
                'ok' => true,
                'message' => 'Transportista eliminado.',
            ], 200);
        }

        return redirect()->route('traffic.transportistas.index')->with('ok', 'Transportista eliminado.');
    }

    public function bulkDestroy(Request $request)
    {
        $data = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer', 'exists:transportistas,id'],
        ]);

        $ids = array_values(array_filter(array_map('intval', $data['ids'] ?? [])));
        if (empty($ids)) {
            return back()->withErrors('Selecciona al menos un transportista para eliminar.');
        }

        $deleted = Transportista::whereIn('id', $ids)->delete();

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'ok' => true,
                'deleted' => $deleted,
                'message' => "{$deleted} transportista(s) eliminado(s).",
            ]);
        }

        return redirect()
            ->route('traffic.transportistas.index')
            ->with('ok', "{$deleted} transportista(s) eliminado(s).");
    }

    public function export()
    {
        $transportistas = Transportista::with(['user', 'supplier', 'bank', 'defaultPaymentMethod.bank', 'liquidationMeta', 'sharedZones'])
            ->orderBy('name')
            ->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Transportistas');

        $headers = [
            'ID',
            'Nombre',
            'Email',
            'Teléfono',
            'Teléfono Alt.',
            'CUIT / DNI',
            'Dirección',
            'Banco',
            'CBU / Alias',
            'Tipo Período',
            'Activo',
            'Zonas'
        ];

        $sheet->fromArray($headers, null, 'A1');

        $sheet->getStyle('A1:L1')->getFont()->setBold(true);
        $sheet->getStyle('A1:L1')->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setRGB('1E3A8A');
        $sheet->getStyle('A1:L1')->getFont()->getColor()->setRGB('FFFFFF');

        $rowIdx = 2;
        foreach ($transportistas as $t) {
            $userEmail = $t->user ? $t->user->email : $t->email;
            $userName = $t->name;
            $bankName = $t->bank ? $t->bank->name : '';
            $cbuAlias = $t->cbu_alias;
            $periodType = optional($t->liquidationMeta)->tipo_periodo ?? '';
            $zones = $t->sharedZones->pluck('name')->implode(', ');

            $rowData = [
                $t->id,
                $userName,
                $userEmail,
                $t->phone,
                $t->phone_alt,
                $t->cuit,
                $t->address_street ? "{$t->address_street} {$t->address_number}, {$t->address_locality}" : '',
                $bankName,
                $cbuAlias,
                $periodType,
                $t->is_active ? 'Si' : 'No',
                $zones
            ];

            $sheet->fromArray($rowData, null, "A{$rowIdx}");
            $rowIdx++;
        }

        foreach (range('A', 'L') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $filename = 'transportistas_y_usuarios_' . date('Y-m-d_H-i') . '.xlsx';

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header("Content-Disposition: attachment; filename=\"{$filename}\"");
        header('Cache-Control: max-age=0');

        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
        $writer->save('php://output');
        exit;
    }

    public function import(Request $request, TermsAcceptanceService $termsService)
    {
        $data = $request->validate([
            'mode' => ['required', Rule::in(['single', 'dual'])],
            'file' => ['required_if:mode,single', 'file', 'mimes:xlsx,xls,csv'],
            'transportistas_file' => ['required_if:mode,dual', 'file', 'mimes:xlsx,xls,csv'],
            'transportes_file' => ['required_if:mode,dual', 'file', 'mimes:xlsx,xls,csv'],
            'transportistas_sheet' => ['nullable', 'string', 'max:120'],
            'transportes_sheet' => ['nullable', 'string', 'max:120'],
            'transportistas_match_column' => ['nullable', 'string', 'max:120'],
            'transportes_match_column' => ['nullable', 'string', 'max:120'],
            'phone_normalization' => ['nullable', 'string'],
        ]);

        $mode = $data['mode'];
        $transportistasFile = $mode === 'single' ? $request->file('file') : $request->file('transportistas_file');
        $transportesFile = $mode === 'single' ? $request->file('file') : $request->file('transportes_file');
        $transportistasSheet = $data['transportistas_sheet'] ?? null;
        $transportesSheet = $data['transportes_sheet'] ?? null;
        $transportistasMatch = $data['transportistas_match_column'] ?? 'Chofer';
        $transportesMatch = $data['transportes_match_column'] ?? 'Chofer';

        if (! $transportistasFile || ! $transportesFile) {
            return back()->withErrors('Debes seleccionar los archivos para importar.');
        }

        $warnings = [];
        $phoneWarnings = [];
        $phoneNormalizationByMatch = [];
        $rawPhoneNormalization = $request->input('phone_normalization');
        if (is_string($rawPhoneNormalization) && trim($rawPhoneNormalization) !== '') {
            $decoded = json_decode($rawPhoneNormalization, true);
            if (is_array($decoded) && is_array($decoded['by_match'] ?? null)) {
                $phoneNormalizationByMatch = $decoded['by_match'];
            }
        }
        $transportistaFormat = TransportistaImportFormat::first();
        $transporteFormat = TransporteImportFormat::first();

        $transportistasData = $transportistaFormat && !empty($transportistaFormat->field_mappings)
            ? $this->readSheetWithMappings($transportistasFile, $transportistasSheet, $transportistaFormat)
            : $this->readSheetWithHeaders($transportistasFile, $transportistasSheet);
        $transportesData = $transporteFormat && !empty($transporteFormat->field_mappings)
            ? $this->readSheetWithMappings($transportesFile, $transportesSheet, $transporteFormat)
            : $this->readSheetWithHeaders($transportesFile, $transportesSheet);

        $transportistasMatchKey = $this->normalizeHeader($transportistasMatch);
        $transportesMatchKey = $this->normalizeHeader($transportesMatch);

        if (!isset($transportistasData['headers'][$transportistasMatchKey])) {
            return back()->withErrors("No se encontró la columna '{$transportistasMatch}' en la hoja de transportistas.");
        }
        if (!isset($transportesData['headers'][$transportesMatchKey])) {
            return back()->withErrors("No se encontró la columna '{$transportesMatch}' en la hoja de transportes.");
        }

        $transportistasDefaultAssigned = [];
        $transportistasMap = [];
        $createdTransportistas = 0;
        $updatedTransportistas = 0;
        $createdTransportes = 0;
        $updatedTransportes = 0;

        foreach ($transportistasData['rows'] as $row) {
            $matchValue = trim((string) ($row[$transportistasMatchKey] ?? ''));
            if ($matchValue === '') {
                continue;
            }

            $payload = $transportistaFormat && !empty($transportistaFormat->field_mappings)
                ? $this->mapTransportistaRowFromConfig($row, $matchValue)
                : $this->mapTransportistaRow($row, $matchValue);
            $payload['is_active'] = true;
            if (empty($payload['name'])) {
                continue;
            }

            $normKey = $this->normalizeMatchValue($matchValue);
            if (!empty($phoneNormalizationByMatch) && isset($phoneNormalizationByMatch[$normKey]) && is_array($phoneNormalizationByMatch[$normKey])) {
                $entry = $phoneNormalizationByMatch[$normKey];
                foreach (['phone', 'phone_alt'] as $fieldKey) {
                    if (!isset($entry[$fieldKey]) || !is_array($entry[$fieldKey])) {
                        continue;
                    }
                    $phoneEntry = $entry[$fieldKey];
                    $ok = (bool) ($phoneEntry['ok'] ?? false);
                    $original = isset($phoneEntry['original']) ? trim((string) $phoneEntry['original']) : null;
                    $e164 = isset($phoneEntry['e164']) ? trim((string) $phoneEntry['e164']) : '';
                    $reason = isset($phoneEntry['reason']) ? trim((string) $phoneEntry['reason']) : 'Teléfono inválido.';
                    $rowNumber = $entry['row'] ?? null;
                    $carrierName = $entry['transportista'] ?? ($payload['name'] ?? $matchValue);

                    if ($original === null || $original === '') {
                        continue;
                    }

                    if ($ok && $e164 !== '') {
                        $payload[$fieldKey] = $e164;
                    } elseif (!$ok) {
                        $payload[$fieldKey] = null;
                        $phoneWarnings[] = [
                            'row' => $rowNumber,
                            'transportista' => $carrierName,
                            'field' => $fieldKey,
                            'original' => $original,
                            'reason' => $reason,
                        ];
                    }
                }
            }

            $zoneNames = $this->parseZoneNames($payload['zones'] ?? null);
            unset($payload['zones']);

            $key = $this->normalizeMatchValue($matchValue);
            $rowId = isset($row['id']) ? (int) $row['id'] : (isset($payload['id']) ? (int) $payload['id'] : null);
            $cuit = !empty($payload['cuit']) ? trim((string) $payload['cuit']) : null;
            $existing = null;

            if ($rowId && $rowId > 0) {
                $existing = Transportista::find($rowId);
            }
            if (!$existing && $cuit) {
                $existing = Transportista::where('cuit', $cuit)->first();
            }
            if (!$existing && !empty($payload['name'])) {
                $existing = Transportista::whereRaw('LOWER(name) = ?', [strtolower($payload['name'])])->first();
            }

            $cleanPayload = Arr::except($payload, ['id']);
            if ($existing) {
                $existing->fill($cleanPayload);
                $existing->save();
                $transportista = $existing;
                $updatedTransportistas++;
            } else {
                $transportista = Transportista::create($cleanPayload);
                $createdTransportistas++;
            }

            $this->ensureTransportistaUser($transportista, $payload['email'] ?? null, $payload['name'] ?? null, $termsService, $warnings);
            if (!empty($zoneNames)) {
                $this->assignSharedZonesByNames($transportista, $zoneNames, $warnings);
            }
            $transportistasMap[$key] = $transportista;
        }

        foreach ($transportesData['rows'] as $row) {
            $matchValue = trim((string) ($row[$transportesMatchKey] ?? ''));
            if ($matchValue === '') {
                continue;
            }

            $key = $this->normalizeMatchValue($matchValue);
            $transportista = $transportistasMap[$key]
                ?? Transportista::whereRaw('LOWER(name) = ?', [strtolower($matchValue)])->first();
            if (! $transportista) {
                $warnings[] = "No se encontró transportista para '{$matchValue}' (transporte omitido).";
                continue;
            }

            if (!isset($transportistasDefaultAssigned[$transportista->id])) {
                $transportistasDefaultAssigned[$transportista->id] = $transportista->transportes()->where('is_default', true)->exists();
            }

            $payload = $transporteFormat && !empty($transporteFormat->field_mappings)
                ? $this->mapTransporteRowFromConfig($row)
                : $this->mapTransporteRow($row);
            $payload['transportista_id'] = $transportista->id;
            if (empty($payload['alias'])) {
                $aliasSource = $payload['license_plate'] ?: $payload['type'] ?: 'Transporte';
                $payload['alias'] = $aliasSource;
            }

            $existing = null;
            if (! empty($payload['license_plate'])) {
                $existing = Transporte::where('transportista_id', $transportista->id)
                    ->where('license_plate', $payload['license_plate'])
                    ->first();
            }

            if ($existing) {
                $payload['is_default'] = $existing->is_default;
            } elseif (!$transportistasDefaultAssigned[$transportista->id]) {
                $payload['is_default'] = true;
                $transportistasDefaultAssigned[$transportista->id] = true;
            } else {
                $payload['is_default'] = false;
            }

            if ($existing) {
                $existing->fill($payload);
                $existing->save();
                $updatedTransportes++;
            } else {
                Transporte::create($payload);
                $createdTransportes++;
            }
        }

        $message = "Transportistas: {$createdTransportistas} creados, {$updatedTransportistas} actualizados. ";
        $message .= "Transportes: {$createdTransportes} creados, {$updatedTransportes} actualizados.";

        $report = [
            'imported_ok' => $createdTransportistas + $updatedTransportistas,
            'created_transportistas' => $createdTransportistas,
            'updated_transportistas' => $updatedTransportistas,
            'created_transportes' => $createdTransportes,
            'updated_transportes' => $updatedTransportes,
            'phone_warnings' => $phoneWarnings,
            'other_warnings' => $warnings,
        ];

        return redirect()
            ->route('traffic.transportistas.index')
            ->with('ok', $message)
            ->with('warnings', $warnings)
            ->with('import_report', $report);
    }

    public function getTransportes(Transportista $transportista)
    {
        $transportes = $transportista->transportes()
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
                'is_active',
                'is_default',
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
                    'is_active'     => $transporte->is_active,
                    'is_default'    => $transporte->is_default,
                ];
            });

        return response()->json([
            'data' => $transportes,
        ]);
    }

    public function getZones(Transportista $transportista)
    {
        return response()->json([
            'data' => $transportista->zones()->orderBy('priority')->orderBy('name')->get(),
        ]);
    }

    public function getSharedZones(Transportista $transportista)
    {
        $assigned = $transportista->sharedZones()->pluck('traffic_zones.id')->all();
        $zones = TrafficZone::orderBy('name')->get()->map(function (TrafficZone $zone) use ($assigned) {
            return [
                'id' => $zone->id,
                'name' => $zone->name,
                'type' => $zone->type,
                'center_lat' => $zone->center_lat,
                'center_lng' => $zone->center_lng,
                'radius_km' => $zone->radius_km,
                'polygon' => $zone->polygon,
                'priority' => $zone->priority,
                'is_soft' => $zone->is_soft,
                'max_stops' => $zone->max_stops,
                'assigned' => in_array($zone->id, $assigned, true),
            ];
        });

        return response()->json([
            'data' => $zones,
        ]);
    }

    public function syncSharedZones(Request $request, Transportista $transportista)
    {
        $data = $request->validate([
            'zone_ids' => ['nullable', 'array'],
            'zone_ids.*' => ['integer', 'exists:traffic_zones,id'],
        ]);

        $zoneIds = array_values(array_unique(array_map('intval', $data['zone_ids'] ?? [])));
        $transportista->sharedZones()->sync($zoneIds);

        return response()->json([
            'ok' => true,
        ]);
    }

    public function storeZone(Request $request, Transportista $transportista)
    {
        $data = $this->validatedZone($request);
        $zone = $transportista->zones()->create($data);

        return response()->json([
            'ok' => true,
            'data' => $zone,
        ], 201);
    }

    public function updateZone(Request $request, Transportista $transportista, TransportistaZone $zone)
    {
        if ($zone->transportista_id !== $transportista->id) {
            abort(404);
        }

        $data = $this->validatedZone($request);
        $zone->update($data);

        return response()->json([
            'ok' => true,
            'data' => $zone,
        ]);
    }

    public function destroyZone(Transportista $transportista, TransportistaZone $zone)
    {
        if ($zone->transportista_id !== $transportista->id) {
            abort(404);
        }

        $zone->delete();

        return response()->json([
            'ok' => true,
        ]);
    }

    private function validatedData(Request $request, ?Transportista $transportista = null): array
    {
        $userRule = Rule::exists('users', 'id')
            ->where(fn ($query) => $query->where('role', User::ROLE_TRANSPORTISTA));

        $uniqueUserRule = Rule::unique('transportistas', 'user_id');
        if ($transportista) {
            $uniqueUserRule = $uniqueUserRule->ignore($transportista->id);
        }

        $rules = [
            'user_id' => ['nullable', $userRule, $uniqueUserRule],
            'name' => ['required', 'string', 'max:255'],
            'business_name' => ['nullable', 'string', 'max:255'],
            'tax_id' => ['nullable', 'string', 'max:100'],
            'dni' => ['nullable', 'string', 'max:50'],
            'phone' => ['nullable', 'string', 'max:50'],
            'phone_alt' => ['nullable', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:255'],
            'license_number' => ['nullable', 'string', 'max:100'],
            'license_expires_at' => ['nullable', 'date'],
            'base_location' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'max:50'],
            'condition' => ['nullable', 'string', 'max:80'],
            'client_name' => ['nullable', 'string', 'max:255'],
            'birth_date' => ['nullable', 'date'],
            'personal_insurance' => ['nullable', 'string', 'max:120'],
            'address_certificate' => ['sometimes', 'boolean'],
            'criminal_record_certificate' => ['sometimes', 'boolean'],
            'cbu' => ['nullable', 'string', 'max:120'],
            'bank_id' => ['nullable', 'exists:banks,id'],
            'account_number' => ['nullable', 'string', 'max:120'],
            'monotributo' => ['nullable', 'string', 'max:120'],
            'hire_date' => ['nullable', 'date'],
            'supplier_id' => ['nullable', 'exists:suppliers,id'],
            'cost_efficiency' => ['nullable', 'numeric', 'min:0', 'max:9999'],
            'performance_weight' => ['nullable', 'numeric', 'min:0', 'max:9999'],
            'color' => ['nullable', 'string', 'regex:/^#([A-Fa-f0-9]{6})$/'],
            'notes' => ['nullable', 'string', 'max:500'],
            'is_active' => ['sometimes', 'boolean'],
            'portal_visibility' => ['nullable', Rule::in(['all', 'routes_only', 'settlements_only'])],
            'advance_blocked_until' => ['nullable', 'date'],
            'liquidation_tipo_periodo' => ['nullable', Rule::in(['quincenal', 'mensual'])],
        ];

        $validated = $request->validate($rules);
        $validated['is_active'] = $request->boolean('is_active', true);
        $validated['address_certificate'] = $request->boolean('address_certificate', false);
        $validated['criminal_record_certificate'] = $request->boolean('criminal_record_certificate', false);
        $validated['cost_efficiency'] = $validated['cost_efficiency'] ?? 0;
        $validated['performance_weight'] = $validated['performance_weight'] ?? 1;

        return $validated;
    }

    private function syncLiquidationMeta(Transportista $transportista, array $data): void
    {
        $tipoPeriodo = $data['liquidation_tipo_periodo'] ?? null;
        if ($tipoPeriodo === null || $tipoPeriodo === '') {
            return;
        }

        $transportista->liquidationMeta()->updateOrCreate(
            ['transportista_id' => $transportista->id],
            ['tipo_periodo' => $tipoPeriodo]
        );
    }

    private function validatedZone(Request $request): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(['circle', 'polygon'])],
            'center_lat' => ['nullable', 'numeric', 'between:-90,90'],
            'center_lng' => ['nullable', 'numeric', 'between:-180,180'],
            'radius_km' => ['nullable', 'numeric', 'min:0'],
            'polygon' => ['nullable'],
            'priority' => ['required', Rule::in(['primary', 'secondary'])],
            'is_soft' => ['sometimes', 'boolean'],
            'max_stops' => ['nullable', 'integer', 'min:1'],
        ];

        $data = $request->validate($rules);
        $data['is_soft'] = $request->boolean('is_soft', false);

        if ($data['type'] === 'circle') {
            if (!isset($data['center_lat'], $data['center_lng'], $data['radius_km'])) {
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

    private function freeTransportistaUsers(): Collection
    {
        // Obtener solo usuarios con rol transportista que NO tengan transportista asignado
        return User::where('role', User::ROLE_TRANSPORTISTA)
            ->whereDoesntHave('transportistaProfile')
            ->orderBy('name')
            ->get();
    }

    private function createOrUpdateUser(array $data, ?Transportista $transportista): ?User
    {
        // Si no hay email o nombre, no crear usuario
        if (empty($data['email']) || empty($data['name'])) {
            return null;
        }

        $password = Str::random(10);
        
        // Si el transportista ya tiene usuario, actualizarlo
        if ($transportista && $transportista->user_id) {
            $user = User::find($transportista->user_id);
            if ($user) {
                $user->update([
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'role' => User::ROLE_TRANSPORTISTA,
                ]);
                return $user;
            }
        }

        // Si ya existe un usuario con este email, reutilizarlo
        $existingUser = User::where('email', $data['email'])->first();
        if ($existingUser) {
            $existingUser->update([
                'name' => $data['name'],
                'role' => User::ROLE_TRANSPORTISTA,
            ]);
            return $existingUser;
        }

        // Crear nuevo usuario
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($password),
            'role' => User::ROLE_TRANSPORTISTA,
        ]);

        $shouldValidate = SystemParameter::bool('valida_transportista', true);
        if ($shouldValidate) {
            app(TermsAcceptanceService::class)->send($user, true, $password);
        } else {
            if (! $user->hasAcceptedTerms()) {
                $user->markAccepted();
            }
            Mail::to($user->email)->send(new UserCreatedMail($user, $password));
        }

        return $user;
    }

    private function ensureTransportistaUser(Transportista $transportista, ?string $email, ?string $name, TermsAcceptanceService $termsService, array &$warnings): void
    {
        if (empty($email)) {
            $warnings[] = "Sin email para crear usuario de {$transportista->name}.";
            return;
        }

        $password = Str::random(10);
        $user = null;
        $isNewUser = false;

        if ($transportista->user_id) {
            $user = User::find($transportista->user_id);
            if ($user) {
                $user->update([
                    'name' => $name ?: $user->name,
                    'email' => $email,
                    'role' => User::ROLE_TRANSPORTISTA,
                ]);
            }
        }

        if (! $user) {
            $existingUser = User::where('email', $email)->first();
            if ($existingUser) {
                $existingUser->update([
                    'name' => $name ?: $existingUser->name,
                    'role' => User::ROLE_TRANSPORTISTA,
                ]);
                $user = $existingUser;
            } else {
                $user = User::create([
                    'name' => $name ?: $email,
                    'email' => $email,
                    'password' => Hash::make($password),
                    'role' => User::ROLE_TRANSPORTISTA,
                ]);
                $isNewUser = true;
            }
        }

        if ($user) {
            $transportista->update(['user_id' => $user->id]);
            if ($user->transportista_id !== $transportista->id) {
                $user->update(['transportista_id' => $transportista->id]);
            }
            $shouldValidate = SystemParameter::bool('valida_transportista', true);
            if ($shouldValidate) {
                if ($isNewUser || ! $user->hasAcceptedTerms()) {
                    $termsService->send($user, true, $password);
                }
            } else {
                if (! $user->hasAcceptedTerms()) {
                    $user->markAccepted();
                }
                if ($isNewUser) {
                    Mail::to($user->email)->send(new UserCreatedMail($user, $password));
                }
            }
        } else {
            $warnings[] = "No se pudo crear usuario para {$transportista->name}.";
        }
    }

    private function readSheetWithHeaders($file, ?string $sheet): array
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $rows = [];

        if (class_exists(IOFactory::class) && in_array($extension, ['xlsx', 'xls'], true)) {
            $spreadsheet = IOFactory::load($file->getRealPath());
            $worksheet = $this->selectWorksheet($spreadsheet, $sheet);
            $rows = $worksheet->toArray(null, true, true, true);
        } else {
            if (($handle = fopen($file->getRealPath(), 'r')) !== false) {
                $rowIndex = 0;
                while (($row = fgetcsv($handle, 0, ',')) !== false) {
                    $rowIndex++;
                    $rows[$rowIndex] = $row;
                }
                fclose($handle);
            }
        }

        if (empty($rows)) {
            return ['headers' => [], 'rows' => []];
        }

        $headerRow = array_shift($rows);
        $headers = [];
        foreach ($headerRow as $columnKey => $value) {
            $normalized = $this->normalizeHeader((string) $value);
            if ($normalized !== '') {
                $headers[$normalized] = $columnKey;
            }
        }

        $dataRows = [];
        foreach ($rows as $row) {
            $mapped = [];
            foreach ($headers as $header => $columnKey) {
                $mapped[$header] = $row[$columnKey] ?? null;
            }
            $dataRows[] = $mapped;
        }

        return ['headers' => $headers, 'rows' => $dataRows];
    }

    private function readSheetWithMappings($file, ?string $sheet, $format): array
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $rows = [];

        if (class_exists(IOFactory::class) && in_array($extension, ['xlsx', 'xls'], true)) {
            $spreadsheet = IOFactory::load($file->getRealPath());
            $worksheet = $this->selectWorksheet($spreadsheet, $sheet ?: $format->sheet);
            $rows = $worksheet->toArray(null, true, true, true);
        } else {
            if (($handle = fopen($file->getRealPath(), 'r')) !== false) {
                $rowIndex = 0;
                while (($row = fgetcsv($handle, 0, ',')) !== false) {
                    $rowIndex++;
                    $rows[$rowIndex] = $row;
                }
                fclose($handle);
            }
        }

        $startRow = max((int) ($format->start_row ?? 1), 1);
        $headerRowIndex = max(1, $startRow - 1);
        $headers = [];

        $headerRow = $rows[$headerRowIndex] ?? null;
        $headerColumns = [];
        if (is_array($headerRow)) {
            foreach ($headerRow as $columnKey => $value) {
                $normalized = $this->normalizeHeader((string) $value);
                if ($normalized !== '') {
                    $headers[$normalized] = true;
                    $headerColumns[$normalized] = $columnKey;
                }
            }
        }

        $mappedRows = [];
        foreach ($rows as $rowNumber => $row) {
            if ((int) $rowNumber < $startRow) {
                continue;
            }
            $mapped = [];
            foreach ((array) $format->field_mappings as $field => $config) {
                $column = $config['column'] ?? null;
                if (!$column) {
                    continue;
                }
                $mapped[$field] = $this->readMappedCell($row, $column);
            }
            foreach ($headerColumns as $normalized => $columnKey) {
                if (array_key_exists($normalized, $mapped)) {
                    continue;
                }
                $mapped[$normalized] = $row[$columnKey] ?? null;
            }
            $mappedRows[] = $mapped;
        }

        foreach (array_keys((array) $format->field_mappings) as $fieldKey) {
            $headers[$fieldKey] = true;
        }

        return ['headers' => $headers, 'rows' => $mappedRows];
    }

    private function readMappedCell(array $row, string $column): ?string
    {
        $column = strtoupper(trim($column));
        if ($column === '') {
            return null;
        }
        if (array_key_exists($column, $row)) {
            return $row[$column];
        }
        $values = array_values($row);
        $index = $this->columnLetterToIndex($column);
        return $values[$index] ?? null;
    }

    private function selectWorksheet(Spreadsheet $spreadsheet, ?string $sheetValue): Worksheet
    {
        $candidate = trim((string) ($sheetValue ?? ''));
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

    private function normalizeHeader(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        $value = \Illuminate\Support\Str::lower(\Illuminate\Support\Str::of($value)->ascii());
        $value = preg_replace('/[^a-z0-9]+/', ' ', $value);
        $value = trim(preg_replace('/\s+/', ' ', $value));
        return $value;
    }

    private function normalizeMatchValue(string $value): string
    {
        $value = \Illuminate\Support\Str::lower(\Illuminate\Support\Str::of($value)->ascii());
        $value = preg_replace('/\s+/', ' ', trim($value));
        return $value;
    }

    private function mapTransportistaRowFromConfig(array $row, string $fallbackName): array
    {
        $status = trim((string) ($row['status'] ?? ''));
        $condition = trim((string) ($row['condition'] ?? ''));
        $name = trim((string) ($row['name'] ?? ''));
        $contact = trim((string) ($row['phone'] ?? ''));
        $zone = trim((string) ($row['base_location'] ?? ''));
        $zonesList = trim((string) ($row['zones'] ?? ''));
        $client = trim((string) ($row['client_name'] ?? ''));
        $phoneAlt = trim((string) ($row['phone_alt'] ?? ''));
        $dni = trim((string) ($row['dni'] ?? ''));
        $birthDate = $this->parseDateValue($row['birth_date'] ?? null);
        $licenseExpires = $this->parseDateValue($row['license_expires_at'] ?? null);
        $insurance = trim((string) ($row['personal_insurance'] ?? ''));
        $addressCert = $this->parseBooleanValue($row['address_certificate'] ?? null);
        $email = trim((string) ($row['email'] ?? ''));
        $criminalCert = $this->parseBooleanValue($row['criminal_record_certificate'] ?? null);
        $cbu = trim((string) ($row['cbu'] ?? ''));
        $taxId = trim((string) ($row['tax_id'] ?? ''));
        $monotributo = trim((string) ($row['monotributo'] ?? ''));
        $hireDate = $this->parseDateValue($row['hire_date'] ?? null);

        $normalizedStatus = $this->normalizeMatchValue($status ?? '');

        return [
            'name' => $name ?: $fallbackName,
            'status' => $status,
            'condition' => $condition,
            'phone' => $contact,
            'base_location' => $zone,
            'zones' => $zonesList,
            'client_name' => $client,
            'phone_alt' => $phoneAlt,
            'dni' => $dni,
            'birth_date' => $birthDate,
            'license_expires_at' => $licenseExpires,
            'personal_insurance' => $insurance,
            'address_certificate' => $addressCert,
            'email' => $email,
            'criminal_record_certificate' => $criminalCert,
            'cbu' => $cbu,
            'tax_id' => $taxId,
            'monotributo' => $monotributo,
            'hire_date' => $hireDate,
            'is_active' => $normalizedStatus === 'activo' ? true : false,
        ];
    }

    private function mapTransporteRowFromConfig(array $row): array
    {
        $status = trim((string) ($row['status'] ?? ''));
        $licensePlate = trim((string) ($row['license_plate'] ?? ''));
        $type = trim((string) ($row['type'] ?? ''));
        $unitColor = trim((string) ($row['unit_color'] ?? ''));
        $brand = trim((string) ($row['brand'] ?? ''));
        $model = trim((string) ($row['model'] ?? ''));
        $version = trim((string) ($row['version'] ?? ''));
        $year = $row['year'] ?? null;
        $chassis = trim((string) ($row['chassis_number'] ?? ''));
        $fuel = trim((string) ($row['fuel_type'] ?? ''));
        $registration = trim((string) ($row['registration_card'] ?? ''));
        $vtv = trim((string) ($row['vtv'] ?? ''));
        $insurance = trim((string) ($row['insurance'] ?? ''));
        $satellite = trim((string) ($row['satellite'] ?? ''));
        $doors = $row['doors_count'] ?? null;
        $tank = trim((string) ($row['tank_capacity'] ?? ''));
        $consumption = trim((string) ($row['fuel_consumption_avg'] ?? ''));
        $driver = trim((string) ($row['driver_name'] ?? ''));
        $owner = trim((string) ($row['owner_name'] ?? ''));

        $normalizedStatus = $this->normalizeMatchValue($status ?? '');

        return [
            'driver_name' => $driver,
            'owner_name' => $owner,
            'status' => $status,
            'alias' => $licensePlate ?: $type,
            'license_plate' => $licensePlate,
            'type' => $type,
            'unit_color' => $unitColor,
            'brand' => $brand,
            'model' => $model,
            'version' => $version,
            'year' => is_numeric($year) ? (int) $year : null,
            'chassis_number' => $chassis,
            'fuel_type' => $fuel,
            'registration_card' => $registration,
            'vtv' => $vtv,
            'insurance' => $insurance,
            'satellite' => $satellite,
            'doors_count' => is_numeric($doors) ? (int) $doors : null,
            'tank_capacity' => $tank,
            'fuel_consumption_avg' => $consumption,
            'is_active' => $normalizedStatus === 'activo' ? true : false,
        ];
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

    private function mapTransportistaRow(array $row, string $fallbackName): array
    {
        $status = $this->valueFromRow($row, ['estado']);
        $condition = $this->valueFromRow($row, ['condicion', 'condicion de contratacion']);
        $name = $this->valueFromRow($row, ['chofer', 'conductor', 'driver']);
        $contact = $this->valueFromRow($row, ['contacto', 'telefono', 'tel']);
        $zone = $this->valueFromRow($row, ['zona']);
        $zonesList = $this->valueFromRow($row, ['zonas', 'zonas comunes', 'zones', 'zones comunes']);
        $client = $this->valueFromRow($row, ['cliente']);
        $phoneAlt = $this->valueFromRow($row, ['numero alternativo', 'nro alternativo', 'telefono alternativo']);
        $dni = $this->valueFromRow($row, ['dni']);
        $birthDate = $this->parseDateValue($this->valueFromRow($row, ['fecha de nacimiento', 'fecha nacimiento']));
        $licenseExpires = $this->parseDateValue($this->valueFromRow($row, ['registro', 'vencimiento registro']));
        $insurance = $this->valueFromRow($row, ['seguro personal', 'seguro']);
        $addressCert = $this->parseBooleanValue($this->valueFromRow($row, ['cert de domicilio', 'cert domicilio', 'certificado domicilio']));
        $email = $this->valueFromRow($row, ['email', 'correo']);
        $criminalCert = $this->parseBooleanValue($this->valueFromRow($row, ['cer antecedentes penales', 'cert antecedentes penales', 'antecedentes penales']));
        $cbu = $this->valueFromRow($row, ['cbu']);
        $taxId = $this->valueFromRow($row, ['cuit cuil', 'cuit/cuil', 'cuit', 'cuil']);
        $monotributo = $this->valueFromRow($row, ['monotributo']);
        $hireDate = $this->parseDateValue($this->valueFromRow($row, ['fecha de ingreso', 'fecha ingreso']));

        $normalizedStatus = $this->normalizeMatchValue($status ?? '');

        return [
            'name' => $name ?: $fallbackName,
            'status' => $status,
            'condition' => $condition,
            'phone' => $contact,
            'base_location' => $zone,
            'zones' => $zonesList,
            'client_name' => $client,
            'phone_alt' => $phoneAlt,
            'dni' => $dni,
            'birth_date' => $birthDate,
            'license_expires_at' => $licenseExpires,
            'personal_insurance' => $insurance,
            'address_certificate' => $addressCert,
            'email' => $email,
            'criminal_record_certificate' => $criminalCert,
            'cbu' => $cbu,
            'tax_id' => $taxId,
            'monotributo' => $monotributo,
            'hire_date' => $hireDate,
            'is_active' => $normalizedStatus === 'activo' ? true : false,
        ];
    }

    private function mapTransporteRow(array $row): array
    {
        $status = $this->valueFromRow($row, ['estado']);
        $licensePlate = $this->valueFromRow($row, ['patente']);
        $type = $this->valueFromRow($row, ['tipo']);
        $unitColor = $this->valueFromRow($row, ['color de la unidad', 'color unidad', 'color']);
        $brand = $this->valueFromRow($row, ['marca']);
        $model = $this->valueFromRow($row, ['modelo']);
        $version = $this->valueFromRow($row, ['version', 'versión']);
        $year = $this->valueFromRow($row, ['año', 'anio']);
        $chassis = $this->valueFromRow($row, ['n de chasis', 'nro de chasis', 'numero de chasis', 'nº de chasis']);
        $fuel = $this->valueFromRow($row, ['combustible']);
        $registration = $this->valueFromRow($row, ['cedula verde azul', 'cedula verde/ azul', 'cedula verde azul']);
        $vtv = $this->valueFromRow($row, ['vtv']);
        $insurance = $this->valueFromRow($row, ['seguro']);
        $satellite = $this->valueFromRow($row, ['satelital', 'satellite']);
        $doors = $this->valueFromRow($row, ['cant de puertas', 'cant. de puertas', 'cantidad de puertas']);
        $tank = $this->valueFromRow($row, ['cap tanque', 'cap. tanque', 'capacidad tanque']);
        $consumption = $this->valueFromRow($row, ['prom consumo de comb', 'prom. consumo de comb', 'prom consumo']);
        $driver = $this->valueFromRow($row, ['chofer', 'conductor', 'driver']);
        $owner = $this->valueFromRow($row, ['titular', 'dueno', 'dueño']);

        $normalizedStatus = $this->normalizeMatchValue($status ?? '');

        return [
            'driver_name' => $driver,
            'owner_name' => $owner,
            'status' => $status,
            'alias' => $licensePlate ?: $type,
            'license_plate' => $licensePlate,
            'type' => $type,
            'unit_color' => $unitColor,
            'brand' => $brand,
            'model' => $model,
            'version' => $version,
            'year' => is_numeric($year) ? (int) $year : null,
            'chassis_number' => $chassis,
            'fuel_type' => $fuel,
            'registration_card' => $registration,
            'vtv' => $vtv,
            'insurance' => $insurance,
            'satellite' => $satellite,
            'doors_count' => is_numeric($doors) ? (int) $doors : null,
            'tank_capacity' => $tank,
            'fuel_consumption_avg' => $consumption,
            'is_active' => $normalizedStatus === 'activo' ? true : false,
        ];
    }

    private function valueFromRow(array $row, array $keys): ?string
    {
        foreach ($keys as $key) {
            $normalized = $this->normalizeHeader($key);
            if (array_key_exists($normalized, $row)) {
                $value = $row[$normalized];
                if (is_string($value)) {
                    $value = trim($value);
                }
                if ($value !== null && $value !== '') {
                    return (string) $value;
                }
            }
        }

        return null;
    }

    private function parseBooleanValue(?string $value): bool
    {
        if ($value === null) {
            return false;
        }
        $normalized = $this->normalizeMatchValue($value);
        return in_array($normalized, ['si', 'sí', 'true', '1', 'ok', 'presentado'], true);
    }

    private function parseDateValue(?string $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            try {
                return Carbon::instance(ExcelDate::excelToDateTimeObject($value));
            } catch (\Throwable $e) {
                return null;
            }
        }

        $value = trim((string) $value);

        // Normalize cases where SheetJS SSF date formatting bug drops the second slash:
        // e.g., '27/72026' -> '27/7/2026', '25/072026' -> '25/07/2026'
        if (preg_match('/^(\d{1,2})\/(\d{1,2})(\d{4})$/', $value, $matches)) {
            $value = $matches[1] . '/' . $matches[2] . '/' . $matches[3];
        }

        $formats = [
            'd/m/y',
            'd/m/Y',
            'd-m-y',
            'd-m-Y',
            'Y-m-d',
        ];

        foreach ($formats as $format) {
            try {
                return Carbon::createFromFormat($format, $value);
            } catch (\Throwable $e) {
                continue;
            }
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function geocodeAddress(?string $address): array
    {
        $address = trim((string) $address);
        if ($address === '') {
            return [null, null];
        }

        $apiKey = env('GOOGLE_MAPS_API_KEY');
        if (!$apiKey) {
            return [null, null];
        }

        try {
            $response = Http::get('https://maps.googleapis.com/maps/api/geocode/json', [
                'address' => $address,
                'key' => $apiKey,
            ]);
            if (!$response->ok()) {
                return [null, null];
            }
            $json = $response->json();
            $location = $json['results'][0]['geometry']['location'] ?? null;
            if (!$location) {
                return [null, null];
            }
            return [$location['lat'] ?? null, $location['lng'] ?? null];
        } catch (\Throwable $e) {
            return [null, null];
        }
    }

    private function parseZoneNames(?string $value): array
    {
        if ($value === null) {
            return [];
        }
        $raw = trim((string) $value);
        if ($raw === '') {
            return [];
        }
        $parts = preg_split('/[,;]+/', $raw);
        $names = [];
        foreach ($parts as $part) {
            $name = trim((string) $part);
            if ($name === '') {
                continue;
            }
            $names[] = $name;
        }
        return array_values(array_unique($names));
    }

    private function assignSharedZonesByNames(Transportista $transportista, array $names, array &$warnings): void
    {
        $names = array_values(array_filter(array_map('trim', $names)));
        if (empty($names)) {
            return;
        }

        $zones = TrafficZone::whereIn('name', $names)->get();
        $found = $zones->pluck('name')->map(fn ($name) => (string) $name)->all();
        $missing = array_diff($names, $found);

        if (!empty($missing)) {
            foreach ($missing as $missingName) {
                $warnings[] = "Zona común '{$missingName}' no existe (transportista {$transportista->name}).";
            }
        }

        if ($zones->isNotEmpty()) {
            $transportista->sharedZones()->syncWithoutDetaching($zones->pluck('id')->all());
        }
    }
}
