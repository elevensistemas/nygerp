<?php

namespace App\Services\DriverPayments;

use App\Models\DriverPaymentType;
use App\Models\PlanillaPagoChofer;
use App\Models\PlanillaPagoChoferRecibo;
use App\Models\ReciboChofer;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\IOFactory;

class PlanillaBankResultImporter
{
    public function import(PlanillaPagoChofer $planilla, UploadedFile $file): array
    {
        $spreadsheet = IOFactory::load($file->getRealPath());
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray(null, true, true, true);

        if (count($rows) < 2) {
            return [
                'processed' => 0,
                'updated' => 0,
                'errors' => ['El archivo no contiene filas para conciliar.'],
            ];
        }

        $header = array_shift($rows);
        $headerMap = $this->normalizeHeaderMap($header);

        $processed = 0;
        $updated = 0;
        $errors = [];

        foreach ($rows as $idx => $row) {
            $processed++;
            $reciboId = (int) $this->readByHeader($row, $headerMap, ['recibo id', 'recibo_id']);
            $resultado = strtoupper(trim((string) $this->readByHeader($row, $headerMap, ['resultado'])));

            if ($reciboId <= 0) {
                $errors[] = 'Fila ' . ($idx + 2) . ': recibo_id invalido.';
                continue;
            }

            $link = PlanillaPagoChoferRecibo::where('planilla_pago_chofer_id', $planilla->id)
                ->where('recibo_chofer_id', $reciboId)
                ->first();

            if (! $link) {
                $errors[] = 'Fila ' . ($idx + 2) . ': recibo ' . $reciboId . ' no pertenece a la planilla.';
                continue;
            }

            $recibo = ReciboChofer::find($reciboId);
            if (! $recibo) {
                $errors[] = 'Fila ' . ($idx + 2) . ': recibo no encontrado.';
                continue;
            }

            $paymentType = $this->resolvePaymentTypeByDescription($resultado);
            if (! $paymentType) {
                $errors[] = 'Fila ' . ($idx + 2) . ': resultado invalido (' . $resultado . ').';
                continue;
            }

            $this->applyPaymentType($link, $recibo, $paymentType);
            $updated++;
        }

        $this->refreshPlanillaStatus($planilla->fresh('reciboLinks'));

        return [
            'processed' => $processed,
            'updated' => $updated,
            'errors' => $errors,
        ];
    }

    public function updateSingleResult(PlanillaPagoChoferRecibo $link, $resultado): void
    {
        $recibo = $link->recibo;

        $paymentType = null;
        if ($resultado !== null && $resultado !== '') {
            $paymentType = DriverPaymentType::find((int) $resultado);
        }

        if (! $paymentType) {
            $link->driver_payment_type_id = null;
            $link->estado_pago = PlanillaPagoChoferRecibo::ESTADO_SIN_RESULTADO;
            $link->save();
            if ($recibo && $recibo->estado === ReciboChofer::ESTADO_PAGADO) {
                $recibo->estado = ReciboChofer::ESTADO_EN_PLANILLA;
                $recibo->save();
            }
            $this->refreshPlanillaStatus($link->planilla->fresh('reciboLinks'));
            return;
        }

        $this->applyPaymentType($link, $recibo, $paymentType);
        $this->refreshPlanillaStatus($link->planilla->fresh('reciboLinks'));
    }

    private function resolvePaymentTypeByDescription(string $resultado): ?DriverPaymentType
    {
        $resultado = trim($resultado);
        if ($resultado === '' || $resultado === 'SIN_RESULTADO') {
            return null;
        }

        return DriverPaymentType::query()->get()->first(function (DriverPaymentType $paymentType) use ($resultado) {
            return $this->normalizePaymentTypeLabel($paymentType->description) === $this->normalizePaymentTypeLabel($resultado);
        });
    }

    private function normalizePaymentTypeLabel(string $value): string
    {
        $value = mb_strtoupper(trim($value));
        $value = preg_replace('/[^A-Z0-9]+/u', '', $value);

        return (string) $value;
    }

    private function applyPaymentType(PlanillaPagoChoferRecibo $link, ?ReciboChofer $recibo, DriverPaymentType $paymentType): void
    {
        $link->driver_payment_type_id = $paymentType->id;
        $link->estado_pago = $paymentType->type
            ? PlanillaPagoChoferRecibo::ESTADO_PAGADO
            : PlanillaPagoChoferRecibo::ESTADO_PENDIENTE;
        $link->save();

        if ($recibo) {
            $recibo->estado = $paymentType->type
                ? ReciboChofer::ESTADO_PAGADO
                : ReciboChofer::ESTADO_PENDIENTE_PAGO;
            $recibo->save();
        }
    }

    private function refreshPlanillaStatus(PlanillaPagoChofer $planilla): void
    {
        $links = $planilla->reciboLinks;

        if ($links->isEmpty()) {
            $planilla->estado = PlanillaPagoChofer::ESTADO_BORRADOR;
            $planilla->save();
            return;
        }

        $allPaid = $links->every(function (PlanillaPagoChoferRecibo $link) {
            return $link->estado_pago === PlanillaPagoChoferRecibo::ESTADO_PAGADO;
        });

        $anyPaid = $links->contains(function (PlanillaPagoChoferRecibo $link) {
            return $link->estado_pago === PlanillaPagoChoferRecibo::ESTADO_PAGADO;
        });

        if ($allPaid) {
            $planilla->estado = PlanillaPagoChofer::ESTADO_CERRADA;
        } elseif ($anyPaid) {
            $planilla->estado = PlanillaPagoChofer::ESTADO_PAGADA_PARCIAL;
        } else {
            $planilla->estado = PlanillaPagoChofer::ESTADO_CONFIRMADA;
        }

        $planilla->save();
    }

    private function normalizeHeaderMap(array $header): array
    {
        $map = [];
        foreach ($header as $column => $label) {
            $key = strtolower(trim((string) preg_replace('/[^a-z0-9_ ]/i', '', (string) $label)));
            if ($key !== '') {
                $map[$key] = $column;
            }
        }

        return $map;
    }

    private function readByHeader(array $row, array $headerMap, array $aliases)
    {
        foreach ($aliases as $alias) {
            $key = strtolower(trim($alias));
            if (isset($headerMap[$key])) {
                return $row[$headerMap[$key]] ?? null;
            }
        }

        return null;
    }
}
