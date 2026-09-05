<?php

namespace App\Http\Controllers;

use App\Http\Requests\PlanillaImportResultRequest;
use App\Http\Requests\PlanillaManualResultUpdateRequest;
use App\Models\PlanillaPagoChofer;
use App\Models\PlanillaPagoChoferRecibo;
use App\Services\DriverPayments\PlanillaBankResultImporter;
use App\Services\DriverPayments\PlanillaExporter;
use Illuminate\Http\RedirectResponse;

class PlanillaPagoConciliacionController extends Controller
{
    public function exportBankTemplate(PlanillaPagoChofer $planilla, PlanillaExporter $exporter)
    {
        $this->authorize('conciliate', $planilla);

        $spreadsheet = $exporter->buildBankTemplate($planilla);

        return response()->streamDownload(function () use ($spreadsheet, $exporter) {
            $writer = $exporter->writer($spreadsheet);
            $writer->save('php://output');
        }, 'planilla-banco-' . $planilla->numero . '.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function exportSantander(PlanillaPagoChofer $planilla, PlanillaExporter $exporter)
    {
        $this->authorize('conciliate', $planilla);

        try {
            $txtContent = $exporter->buildSantanderTxt($planilla);

            return response($txtContent, 200, [
                'Content-Type' => 'text/plain',
                'Content-Disposition' => 'attachment; filename="santander-pago-' . $planilla->numero . '.txt"',
            ]);
        } catch (\InvalidArgumentException $e) {
            $errors = json_decode($e->getMessage(), true) ?: [$e->getMessage()];
            return redirect()
                ->route('pago-choferes.planillas.show', $planilla)
                ->with('warnings', $errors);
        }
    }

    public function importBankResults(PlanillaImportResultRequest $request, PlanillaPagoChofer $planilla, PlanillaBankResultImporter $importer): RedirectResponse
    {
        $this->authorize('conciliate', $planilla);

        if ($planilla->estado === PlanillaPagoChofer::ESTADO_CERRADA) {
            return redirect()->route('pago-choferes.planillas.show', $planilla)->with('warnings', ['La planilla está cerrada y no admite modificaciones.']);
        }

        $result = $importer->import($planilla, $request->file('file'));

        return redirect()
            ->route('pago-choferes.planillas.show', $planilla)
            ->with('ok', 'Conciliacion importada. Filas actualizadas: ' . $result['updated'])
            ->with('warnings', $result['errors'] ?? []);
    }

    public function updateManualResult(PlanillaManualResultUpdateRequest $request, PlanillaPagoChoferRecibo $link, PlanillaBankResultImporter $importer): RedirectResponse
    {
        $this->authorize('conciliate', $link->planilla);

        if ($link->planilla->estado === PlanillaPagoChofer::ESTADO_CERRADA) {
            return redirect()->route('pago-choferes.planillas.show', $link->planilla)->with('warnings', ['La planilla está cerrada y no admite modificaciones.']);
        }

        $importer->updateSingleResult($link->load('recibo', 'planilla'), $request->validated()['resultado']);

        return redirect()->route('pago-choferes.planillas.show', $link->planilla)->with('ok', 'Resultado actualizado.');
    }

    public function markAllPaid(\Illuminate\Http\Request $request, PlanillaPagoChofer $planilla): RedirectResponse
    {
        $this->authorize('conciliate', $planilla);

        if ($planilla->estado === PlanillaPagoChofer::ESTADO_CERRADA) {
            return redirect()->route('pago-choferes.planillas.show', $planilla)->with('warnings', ['La planilla ya se encuentra cerrada.']);
        }

        $paidType = \App\Models\DriverPaymentType::where('type', true)->first();
        if (!$paidType) {
            return redirect()
                ->route('pago-choferes.planillas.show', $planilla)
                ->with('warnings', ['No se encontró el tipo de pago "Pagado" en el sistema.']);
        }

        $shouldClose = $request->input('close', '1') === '1';

        \Illuminate\Support\Facades\DB::transaction(function () use ($planilla, $paidType, $shouldClose) {
            foreach ($planilla->reciboLinks as $link) {
                $link->driver_payment_type_id = $paidType->id;
                $link->estado_pago = PlanillaPagoChoferRecibo::ESTADO_PAGADO;
                $link->save();

                $recibo = $link->recibo;
                if ($recibo) {
                    $recibo->estado = \App\Models\ReciboChofer::ESTADO_PAGADO;
                    $recibo->save();
                }
            }

            if ($shouldClose) {
                $planilla->estado = PlanillaPagoChofer::ESTADO_CERRADA;
            } else {
                $planilla->estado = PlanillaPagoChofer::ESTADO_PAGADA_PARCIAL;
            }
            $planilla->save();
        });

        $msg = $shouldClose
            ? 'Todos los recibos de la planilla fueron marcados como pagados y la planilla fue cerrada.'
            : 'Todos los recibos de la planilla fueron marcados como pagados (planilla kept open).';

        return redirect()
            ->route('pago-choferes.planillas.show', $planilla)
            ->with('ok', $msg);
    }

    public function exportSantanderSandbox(\Illuminate\Http\Request $request)
    {
        abort_unless(auth()->user() && auth()->user()->isAdminOrSuper(), 403);

        $data = $request->validate([
            'company_cuit' => ['required', 'string'],
            'agreement_number' => ['required', 'string'],
            'beneficiary_id' => ['required', 'string'],
            'period' => ['required', 'string'],
            'beneficiary_name' => ['required', 'string'],
            'beneficiary_cuit' => ['required', 'string'],
            'beneficiary_cbu' => ['required', 'string'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_date' => ['required', 'date'],
        ]);

        $companyCuit = preg_replace('/\D/', '', $data['company_cuit']);
        $agreement = preg_replace('/\D/', '', $data['agreement_number']);
        $benefId = preg_replace('/\D/', '', $data['beneficiary_id']);
        $period = preg_replace('/\D/', '', $data['period']);
        $benefName = $data['beneficiary_name'];
        $benefCuit = preg_replace('/\D/', '', $data['beneficiary_cuit']);
        $benefCbu = preg_replace('/\D/', '', $data['beneficiary_cbu']);
        $amountFloat = (float) $data['amount'];
        $paymentDate = \Carbon\Carbon::parse($data['payment_date'])->format('Ymd');

        $errors = [];
        if (strlen($companyCuit) !== 11) {
            $errors[] = "CUIT de la empresa debe tener 11 dígitos.";
        }
        if (strlen($agreement) > 2 || strlen($agreement) === 0) {
            $errors[] = "Número de acuerdo Santander debe tener 1 o 2 dígitos.";
        }
        if (strlen($benefCuit) !== 11) {
            $errors[] = "CUIT del beneficiario debe tener 11 dígitos.";
        }
        if (strlen($benefCbu) !== 22) {
            $errors[] = "CBU del beneficiario debe tener 22 dígitos.";
        }
        if (strlen($period) !== 6) {
            $errors[] = "El período debe tener formato AAAAMM (6 dígitos).";
        }

        if (!empty($errors)) {
            return back()->with('warnings', $errors)->withInput();
        }

        $companyCuit = str_pad(substr($companyCuit, 0, 11), 11, '0', STR_PAD_LEFT);
        $agreement = str_pad(substr($agreement, 0, 2), 2, '0', STR_PAD_LEFT);
        $header = "H" . $companyCuit . "0" . "013" . $agreement . "0070000100000" . str_repeat(' ', 7) . "S" . str_repeat(' ', 611);

        $benefCode = str_pad(substr($benefId, 0, 15), 15, ' ', STR_PAD_RIGHT);
        $compNum = str_pad(substr($period, 0, 6), 15, '0', STR_PAD_LEFT);

        $sanitizedName = mb_strtoupper(iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $benefName));
        $sanitizedName = (string) preg_replace('/[^A-Z0-9 ]/', '', $sanitizedName);
        $nameField = str_pad(substr($sanitizedName, 0, 30), 30, ' ', STR_PAD_RIGHT);

        $isSantander = str_starts_with($benefCbu, '072');
        $formaPago = $isSantander ? '50' : '52';

        $block1 = substr($benefCbu, 0, 8);
        $block2 = substr($benefCbu, 8, 14);
        $formattedCbu = "0" . $block1 . "000" . $block2;

        $cents = (int) round($amountFloat * 100);

        $detail = "D"
            . " "
            . "0"
            . $benefCode
            . "RC"
            . $compNum
            . "0000"
            . $nameField
            . str_repeat(' ', 51)
            . "00000"
            . "   "
            . "N"
            . str_repeat(' ', 90)
            . "    "
            . str_pad(substr($benefCuit, 0, 11), 11, '0', STR_PAD_LEFT)
            . str_repeat(' ', 162)
            . "N"
            . "0054"
            . $formattedCbu
            . "00000000"
            . $paymentDate
            . str_pad((string) $cents, 15, '0', STR_PAD_LEFT)
            . $formaPago
            . "   "
            . "00000000000"
            . "   "
            . "00000000000"
            . "   "
            . "00000000000"
            . "   "
            . str_repeat('0', 25)
            . " "
            . str_repeat('0', 17)
            . str_repeat(' ', 102);

        $trailer = "T"
            . str_repeat('0', 15)
            . str_pad((string) $cents, 15, '0', STR_PAD_LEFT)
            . str_pad("1", 7, '0', STR_PAD_LEFT)
            . str_repeat(' ', 612);

        $txtContent = $header . "\r\n" . $detail . "\r\n" . $trailer . "\r\n";

        return response($txtContent, 200, [
            'Content-Type' => 'text/plain',
            'Content-Disposition' => 'attachment; filename="santander-pago-test-sandbox.txt"',
        ]);
    }
}
