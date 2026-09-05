<?php

namespace App\Http\Controllers;

use App\Models\DriverPaymentReport;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DriverPaymentReportController extends Controller
{
    public function index(Request $request): View
    {
        $reports = DriverPaymentReport::query()->orderBy('alias')->get();
        $selectedReport = $this->resolveSelectedReport($reports, $request->query('report'));

        return view('pago-choferes.reportes.index', [
            'reports' => $reports,
            'selectedReport' => $selectedReport,
        ]);
    }

    public function pdf(Request $request, DriverPaymentReport $report)
    {
        $rows = $this->runReport($report, $request);
        $columns = $this->extractColumns($rows);

        $pdf = Pdf::loadView('pago-choferes.reportes.pdf', [
            'report' => $report,
            'rows' => $rows,
            'columns' => $columns,
            'generatedAt' => now(),
        ])->setPaper('a4', 'landscape');

        return $pdf->stream('reporte-' . $report->id . '.pdf');
    }

    private function resolveSelectedReport($reports, $selectedId)
    {
        if ($selectedId) {
            $selected = $reports->firstWhere('id', (int) $selectedId);
            if ($selected) {
                return $selected;
            }
        }

        return $reports->first();
    }

    private function runReport(DriverPaymentReport $report, Request $request): array
    {
        $sql = trim((string) $report->sql_query);
        $normalized = strtolower($sql);

        $startsWithSelect = substr($normalized, 0, 6) === 'select';
        $startsWithWith = substr($normalized, 0, 4) === 'with';

        if ($sql === '' || (! $startsWithSelect && ! $startsWithWith)) {
            abort(422, 'El reporte debe usar una consulta SELECT.');
        }

        foreach ([' insert ', ' update ', ' delete ', ' drop ', ' alter ', ' truncate ', ' create '] as $keyword) {
            if (strpos(' ' . $normalized . ' ', $keyword) !== false) {
                abort(422, 'La consulta del reporte contiene operaciones no permitidas.');
            }
        }

        $bindings = [];

        $desde = $request->input('desde', now()->startOfMonth()->format('Y-m-d'));
        $hasta = $request->input('hasta', now()->format('Y-m-d'));
        $tipoReclamo = $request->input('tipo_reclamo', 'todos');

        // Allow passing parameters multiple times to avoid PDO errors on UNION queries
        foreach (['', '1', '2', '3', '4', '5'] as $i) {
            if (strpos($sql, ":desde{$i}") !== false) {
                $bindings["desde{$i}"] = $desde;
            }
            if (strpos($sql, ":hasta{$i}") !== false) {
                $bindings["hasta{$i}"] = $hasta;
            }
            if (strpos($sql, ":tipo_reclamo{$i}") !== false) {
                $bindings["tipo_reclamo{$i}"] = $tipoReclamo;
            }
        }

        return DB::select($sql, $bindings);
    }

    private function extractColumns(array $rows): array
    {
        if (empty($rows)) {
            return [];
        }

        return array_keys((array) $rows[0]);
    }
}
