<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Party;
use App\Services\Geocoder;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;

class TrafficOrderController extends Controller
{
    public function index()
    {
        $orders = Order::withCount(['routes', 'addresses'])
            ->with(['routes' => function ($query) {
                $query->select('id', 'order_id', 'started_at');
            }])
            ->latest()
            ->paginate(20);

        return view('traffic.orders.index', compact('orders'));
    }

    public function create(Request $request)
    {
        $customers = Party::where('role', 'customer')
            ->orderBy('business_name')
            ->orderBy('name')
            ->get();

        $addressesData = [];
        $warnings = [];

        if ($request->filled('file')) {
            $fileParam = $request->query('file');
            $candidatePaths = [];
            if ($fileParam) {
                $candidatePaths[] = Storage::exists($fileParam) ? Storage::path($fileParam) : null;
                $candidatePaths[] = base_path($fileParam);
                $candidatePaths[] = public_path($fileParam);
            }

            $candidatePaths = array_filter($candidatePaths, fn ($p) => $p && is_file($p));
            $filePath = $candidatePaths[0] ?? null;

            if ($filePath) {
                try {
                    [$addressesData, $warnings] = $this->loadAddressesFromFile($filePath, pathinfo($filePath, PATHINFO_EXTENSION));
                } catch (\Throwable $e) {
                    $warnings[] = 'No se pudo leer el archivo proporcionado.';
                }
            } else {
                $warnings[] = 'Archivo no encontrado para importar direcciones.';
            }
        }

        return view('traffic.orders.create', compact('customers', 'addressesData', 'warnings'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'party_id' => 'required|exists:parties,id',
            'order_date' => 'required|date',
            'delivery_instructions' => 'nullable|string',
            'addresses' => 'required|array|min:2',
            'addresses.*.label' => 'nullable|string|max:120',
            'addresses.*.address' => 'required|string|max:255',
            'addresses.*.city' => 'nullable|string|max:120',
            'addresses.*.postal_code' => 'nullable|string|max:50',
            'addresses.*.contact_name' => 'nullable|string|max:120',
            'addresses.*.contact_phone' => 'nullable|string|max:50',
            'addresses.*.notes' => 'nullable|string|max:500',
            'addresses.*.latitude' => 'nullable|numeric',
            'addresses.*.longitude' => 'nullable|numeric',
        ]);

        $party = Party::findOrFail($data['party_id']);
        $order = Order::create([
            'order_number' => $this->generateOrderNumber(),
            'party_id' => $party->id,
            'client_name' => $party->business_name ?: $party->name,
            'client_company' => $party->business_name,
            'client_contact' => $party->name,
            'client_phone' => $party->phone,
            'client_email' => $party->email,
            'order_date' => $data['order_date'],
            'delivery_instructions' => $data['delivery_instructions'] ?? null,
        ]);

        foreach ($data['addresses'] as $index => $address) {
            $order->addresses()->create([
                'sequence' => $index + 1,
                'label' => $address['label'] ?? null,
                'address' => $address['address'],
                'city' => $address['city'] ?? null,
                'postal_code' => $address['postal_code'] ?? null,
                'contact_name' => $address['contact_name'] ?? null,
                'contact_phone' => $address['contact_phone'] ?? null,
                'notes' => $address['notes'] ?? null,
                'latitude'  => $address['latitude'] ?? null,
                'longitude' => $address['longitude'] ?? null,
            ]);
        }

        return redirect()
            ->route('traffic.orders.show', $order)
            ->with('ok', 'Pedido generado. Ahora podés planificar una ruta.');
    }

    public function show(Order $order)
    {
        $order->load(['addresses', 'routes.transportista', 'routes.transporte', 'client']);

        return view('traffic.orders.show', compact('order'));
    }

    public function route(Order $order)
    {
        return redirect()
            ->route('traffic.routes.create', ['order' => $order->id])
            ->with('info', 'Podés planificar una ruta a partir de este pedido.');
    }

    public function destroy(Order $order)
    {
        $order->load('routes.stops');

        $hasStartedRoute = $order->routes->contains(function ($route) {
            return $route->started_at !== null;
        });

        if ($hasStartedRoute) {
            return back()->with('error', 'No se puede eliminar un pedido con rutas iniciadas.');
        }

        // Eliminamos rutas planificadas (no iniciadas) junto a sus paradas
        foreach ($order->routes as $route) {
            $route->stops()->delete();
            $route->delete();
        }

        $order->addresses()->delete();
        $order->delete();

        return redirect()
            ->route('traffic.orders.index')
            ->with('ok', 'Pedido eliminado.');
    }

    public function importAddresses(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv,txt',
            'sheet' => 'nullable|string',
        ]);

        $file = $request->file('file');
        $addresses = [];

        $extension = strtolower((string) $file->getClientOriginalExtension());
        $canUseSpreadsheet = class_exists(\PhpOffice\PhpSpreadsheet\IOFactory::class);
        if (in_array($extension, ['xlsx', 'xls'], true) && !$canUseSpreadsheet) {
            return response()->json([
                'addresses' => [],
                'warnings' => ['No se pudo leer el archivo Excel.'],
            ], 422);
        }

        [$addresses, $warnings] = $this->loadAddressesFromFile(
            $file->getRealPath(),
            $extension,
            $request->input('sheet')
        );

        return response()->json([
            'addresses' => $addresses,
            'warnings' => $warnings,
        ]);
    }

    public function previewAddresses(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv,txt',
        ]);

        $file = $request->file('file');
        $extension = strtolower((string) $file->getClientOriginalExtension());
        $canUseSpreadsheet = class_exists(\PhpOffice\PhpSpreadsheet\IOFactory::class);

        if (in_array($extension, ['xlsx', 'xls'], true) && !$canUseSpreadsheet) {
            return response()->json([
                'error' => 'No se pudo leer el archivo Excel.',
                'columns' => [],
                'sheets' => [],
            ], 422);
        }

        return response()->json(
            $this->loadWorkbookPreview($file->getRealPath(), $extension)
        );
    }

    
    private function generateOrderNumber(): string
    {
        return 'PED-' . now()->format('YmdHis') . '-' . Str::upper(Str::random(4));
    }

    /**
     * @return array{0: array, 1: array}
     */
    private function loadAddressesFromFile(string $path, ?string $extension = null, ?string $sheet = null): array
    {
        $addresses = [];
        $extension = $extension ? strtolower($extension) : strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $rows = [];
        $canUseSpreadsheet = class_exists(\PhpOffice\PhpSpreadsheet\IOFactory::class);

        if ($canUseSpreadsheet && in_array($extension, ['xlsx', 'xls'])) {
            $spreadsheet = IOFactory::load($path);
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
            if (($handle = fopen($path, 'r')) !== false) {
                while (($row = fgetcsv($handle, 0, ',')) !== false) {
                    $rows[] = $row;
                }
                fclose($handle);
            }
        }

        $rowNumber = 0;
        foreach ($rows as $row) {
            $rowNumber++;
            if ($rowNumber < 5) {
                continue; // datos desde fila 5 en adelante
            }

            if (is_array($row) && array_key_exists('D', $row)) {
                $label = trim((string) ($row['A'] ?? ''));
                $notes = trim((string) ($row['C'] ?? ''));
                $addressText = trim((string) ($row['D'] ?? ''));
                $locality = trim((string) ($row['E'] ?? ''));

                if (!$addressText) {
                    continue;
                }

                [$fullAddress, $city] = $this->buildAddressWithLocality($addressText, $locality);

                $addresses[] = [
                    'label' => $label ?: $fullAddress,
                    'address' => $fullAddress,
                    'contact_name' => $label,
                    'contact_phone' => '',
                    'postal_code' => '',
                    'notes' => $notes,
                    'city' => $city,
                    'latitude' => null,
                    'longitude' => null,
                ];
            } else {
                $row = array_map('trim', is_array($row) ? array_values($row) : []);
                if (!count($row)) {
                    continue;
                }

                $label = $row[0] ?? '';
                $notes = $row[2] ?? '';
                $addressText = $row[3] ?? '';
                $locality = $row[4] ?? '';

                if (!trim((string) $addressText)) {
                    continue;
                }

                [$fullAddress, $city] = $this->buildAddressWithLocality($addressText, $locality);

                $addresses[] = [
                    'label' => $label ?: $fullAddress,
                    'address' => $fullAddress,
                    'contact_name' => $label,
                    'contact_phone' => '',
                    'postal_code' => '',
                    'notes' => $notes,
                    'city' => $city,
                    'latitude' => null,
                    'longitude' => null,
                ];
            }
        }

        $geocoder = new Geocoder();
        // Geocodificamos con Google para obtener mejores resultados de provincia y luego usamos Mapbox solo para ruteo.
        [$resolved, $warnings] = $geocoder->geocodeStops(collect($addresses), 'google', true, true);

        $resolved = $resolved->map(function ($address) {
            $lat = $address['lat'] ?? $address['latitude'] ?? null;
            $lng = $address['lng'] ?? $address['longitude'] ?? null;
            $address['is_valid'] = is_numeric($lat) && is_numeric($lng);
            return $address;
        });

        return [$resolved->values()->all(), $warnings];
    }

    /**
     * @return array{columns: array, sheets: array}
     */
    private function loadWorkbookPreview(string $path, ?string $extension = null, int $maxRows = 15): array
    {
        $extension = $extension ? strtolower($extension) : strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $columns = ['A', 'B', 'C', 'D', 'E'];
        $sheets = [];
        $canUseSpreadsheet = class_exists(\PhpOffice\PhpSpreadsheet\IOFactory::class);

        if ($canUseSpreadsheet && in_array($extension, ['xlsx', 'xls'], true)) {
            $spreadsheet = IOFactory::load($path);
            foreach ($spreadsheet->getWorksheetIterator() as $worksheet) {
                $rows = $worksheet->toArray(null, true, true, true);
                $previewRows = [];
                $rowNumber = 0;
                foreach ($rows as $row) {
                    $rowNumber++;
                    if ($rowNumber > $maxRows) {
                        break;
                    }
                    $cells = [];
                    foreach ($columns as $col) {
                        $cells[$col] = isset($row[$col]) ? (string) $row[$col] : '';
                    }
                    $previewRows[] = [
                        'row' => $rowNumber,
                        'cells' => $cells,
                    ];
                }
                $sheets[] = [
                    'name' => $worksheet->getTitle(),
                    'rows' => $previewRows,
                ];
            }
        } else {
            $previewRows = [];
            if (($handle = fopen($path, 'r')) !== false) {
                $rowNumber = 0;
                while (($row = fgetcsv($handle, 0, ',')) !== false) {
                    $rowNumber++;
                    if ($rowNumber > $maxRows) {
                        break;
                    }
                    $cells = [];
                    foreach ($columns as $index => $col) {
                        $cells[$col] = isset($row[$index]) ? (string) $row[$index] : '';
                    }
                    $previewRows[] = [
                        'row' => $rowNumber,
                        'cells' => $cells,
                    ];
                }
                fclose($handle);
            }
            $sheets[] = [
                'name' => 'CSV',
                'rows' => $previewRows,
            ];
        }

        return [
            'columns' => $columns,
            'sheets' => $sheets,
        ];
    }

    private function buildAddressWithLocality(string $address, ?string $locality): array
    {
        $address = trim($address);
        $locality = trim((string) $locality);

        if ($locality === '' || $this->isCabaLocality($locality)) {
            return [$address . ', Ciudad Autonoma de Buenos Aires, Argentina', 'CABA'];
        }

        // Para fuera de CABA, forzamos localidad + provincia + pais para evitar que Mapbox lo lleve a CABA.
        $full = $address . ', ' . $locality . ', Provincia de Buenos Aires, Argentina';
        //var_dump($full);
        return [$full, $locality];
    }

    private function isCabaLocality(string $locality): bool
    {
        $normalized = Str::of($locality)
            ->ascii()
            ->lower()
            ->replace('.', ' ')
            ->replace(',', ' ')
            ->replace('-', ' ')
            ->replace('  ', ' ')
            ->trim()
            ->__toString();

        $tokens = [
            'caba',
            'c a b a',
            'capital federal',
            'ciudad autonoma',
            'ciudad autonoma de buenos aires',
            'ciudad de buenos aires',
        ];

        foreach ($tokens as $token) {
            if (str_contains($normalized, $token)) {
                return true;
            }
        }

        return false;
    }
}
