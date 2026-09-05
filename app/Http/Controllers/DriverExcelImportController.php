<?php

namespace App\Http\Controllers;

use App\Http\Requests\DriverExcelImportRequest;
use App\Models\DriverImportRun;
use App\Models\DriverImportRunRow;
use App\Services\DriverPayments\DriverExcelImporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class DriverExcelImportController extends Controller
{
    public function create(): View
    {
        $this->authorize('import', DriverImportRun::class);

        $logisticsImportModeSetting = \App\Models\DriverImportSetting::where('setting_key', 'driver_payment_logistics_import_mode')->first();

        return view('pago-choferes.import.create', [
            // Avoid expensive DB reads on first load. Import history is available
            // through each result page after processing.
            'latestRuns' => collect(),
            'logisticsImportMode' => is_string(optional($logisticsImportModeSetting)->setting_value) ? $logisticsImportModeSetting->setting_value : 'replace',
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('import', DriverImportRun::class);
 
        $request->validate([
            'original_filename' => ['required', 'string'],
            'quincena_option' => ['nullable', 'string', 'in:ambas,1,2'],
        ]);
 
        try {
            $run = DriverImportRun::create([
                'source_file' => $request->input('original_filename'),
                'tipo_periodo' => 'mixto',
                'created_by' => $request->user() ? $request->user()->id : null,
                'summary' => [
                    'quincena_option' => $request->input('quincena_option', 'ambas'),
                    'touched_receipts' => [],
                ],
                'rows_processed' => 0,
                'rows_created' => 0,
                'rows_updated' => 0,
                'rows_with_errors' => 0,
            ]);
 
            return response()->json([
                'success' => true,
                'run_id' => $run->id,
                'redirect_url' => route('pago-choferes.import.show', $run),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'No se pudo iniciar la importación: ' . $e->getMessage(),
            ], 422);
        }
    }
 
    public function importChunk(Request $request, DriverExcelImporter $importer): JsonResponse
    {
        $request->validate([
            'run_id' => ['required', 'exists:driver_import_runs,id'],
            'chunk' => ['required', 'array'],
            'chunk.sheet_name' => ['required', 'string'],
            'chunk.start_row' => ['required', 'integer'],
            'chunk.rows' => ['required', 'array'],
        ]);
 
        $run = DriverImportRun::findOrFail($request->input('run_id'));
        $this->authorize('import', DriverImportRun::class);
 
        try {
            $result = $importer->importChunk($run, $request->input('chunk'));
            return response()->json([
                'success' => true,
                'result' => $result,
            ]);
        } catch (\Throwable $e) {
            // Log error row if chunk failed as a whole
            $importer->logRow(
                $run,
                $request->input('chunk.sheet_name'),
                (int) $request->input('chunk.start_row'),
                'error',
                'Error procesando lote: ' . $e->getMessage()
            );
            $run->increment('rows_with_errors');
 
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function finishImport(Request $request, DriverExcelImporter $importer): JsonResponse
    {
        $request->validate([
            'run_id' => ['required', 'exists:driver_import_runs,id'],
        ]);

        $run = DriverImportRun::findOrFail($request->input('run_id'));
        $this->authorize('import', DriverImportRun::class);

        try {
            $importer->finishImport($run);
            return response()->json([
                'success' => true,
                'redirect_url' => route('pago-choferes.import.show', $run),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function importFromLogistics(Request $request, DriverExcelImporter $importer)
    {
        $this->authorize('import', DriverImportRun::class);

        $request->validate([
            'fecha_desde' => ['required', 'date'],
            'fecha_hasta' => ['required', 'date', 'after_or_equal:fecha_desde'],
            'quincena_option' => ['nullable', 'string', 'in:ambas,1,2'],
            'import_mode' => ['nullable', 'string', 'in:replace,merge'],
        ]);

        $fechaDesde = \Carbon\Carbon::parse($request->input('fecha_desde'));
        $fechaHasta = \Carbon\Carbon::parse($request->input('fecha_hasta'));
        $quincenaOption = $request->input('quincena_option', 'ambas');
        $requestImportMode = $request->input('import_mode');
        if (empty($requestImportMode)) {
            $logisticsModeSetting = \App\Models\DriverImportSetting::where('setting_key', 'driver_payment_logistics_import_mode')->first();
            $importMode = is_string(optional($logisticsModeSetting)->setting_value) ? $logisticsModeSetting->setting_value : 'replace';
        } else {
            $importMode = $requestImportMode;
        }

        $isJsonRequest = $request->expectsJson() || $request->ajax();

        try {
            $records = \App\Models\DriverLogisticsRecord::with(['transportista.liquidationMeta', 'transporte', 'trafficZone'])
                ->whereBetween('fecha', [$fechaDesde->toDateString(), $fechaHasta->toDateString()])
                ->orderBy('fecha')
                ->get();

            if ($records->isEmpty()) {
                $message = 'No se encontraron registros de logística en el rango de fechas seleccionado.';
                if ($isJsonRequest) {
                    return response()->json([
                        'success' => false,
                        'message' => $message,
                    ], 422);
                }
                return redirect()->back()->withErrors(['message' => $message]);
            }

            $run = DriverImportRun::create([
                'source_file' => 'Logística: ' . $fechaDesde->format('d/m/Y') . ' a ' . $fechaHasta->format('d/m/Y'),
                'tipo_periodo' => 'mixto',
                'created_by' => $request->user() ? $request->user()->id : null,
                'summary' => [
                    'quincena_option' => $quincenaOption,
                    'import_mode' => $importMode,
                    'touched_receipts' => [],
                    'is_logistics' => true,
                ],
                'rows_processed' => 0,
                'rows_created' => 0,
                'rows_updated' => 0,
                'rows_with_errors' => 0,
            ]);

            $result = $importer->importFromLogisticsRecords($run, $records, $quincenaOption, $importMode);

            $targetUrl = route('pago-choferes.recibos.index');

            if ($isJsonRequest) {
                return response()->json([
                    'success' => true,
                    'run_id' => $run->id,
                    'result' => $result,
                    'redirect_url' => $targetUrl,
                ]);
            }

            return redirect()->to($targetUrl)->with('success', 'Se importaron los datos de logística correctamente.');
        } catch (\Throwable $e) {
            $message = 'Error al traer datos de logística: ' . $e->getMessage();
            if ($isJsonRequest) {
                return response()->json([
                    'success' => false,
                    'message' => $message,
                ], 422);
            }
            return redirect()->back()->withErrors(['message' => $message]);
        }
    }

    public function show(DriverImportRun $run): View
    {
        $this->authorize('view', $run);

        $run->load(['rows']);

        return view('pago-choferes.import.show', [
            'run' => $run,
            'rows' => $run->rows()->orderBy('id')->paginate(100),
        ]);
    }

    public function downloadErrors(DriverImportRun $run)
    {
        $this->authorize('view', $run);

        $errors = DriverImportRunRow::query()
            ->where('driver_import_run_id', $run->id)
            ->where('status', 'error')
            ->orderBy('id')
            ->get(['sheet_name', 'row_number', 'message']);

        return response()->streamDownload(function () use ($errors) {
            $output = fopen('php://output', 'w');
            fputcsv($output, ['sheet', 'fila', 'mensaje']);
            foreach ($errors as $error) {
                fputcsv($output, [$error->sheet_name, $error->row_number, $error->message]);
            }
            fclose($output);
        }, 'driver-import-errors-' . $run->id . '.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }
}
