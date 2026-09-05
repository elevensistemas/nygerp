<?php

namespace App\Services\DriverPayments;

use App\Models\PlanillaPagoChofer;
use App\Models\PlanillaPagoChoferRecibo;
use App\Models\SystemParameter;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class PlanillaExporter
{
    public function buildBankTemplate(PlanillaPagoChofer $planilla): Spreadsheet
    {
        $planilla->load(['reciboLinks.recibo.transportista.liquidationMeta']);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Planilla banco');

        $headers = [
            'recibo_id',
            'transportista',
            'cuit',
            'importe_total',
            'plaza',
            'periodo_desde',
            'periodo_hasta',
            'resultado',
        ];

        $sheet->fromArray($headers, null, 'A1');

        $row = 2;
        foreach ($planilla->reciboLinks as $link) {
            $recibo = $link->recibo;
            if (! $recibo) {
                continue;
            }

            $sheet->fromArray([
                $recibo->id,
                optional($recibo->transportista)->name,
                optional($recibo->transportista)->tax_id,
                (float) $link->monto_en_planilla,
                $recibo->plaza,
                optional($recibo->periodo_desde)->format('Y-m-d'),
                optional($recibo->periodo_hasta)->format('Y-m-d'),
                'SIN_RESULTADO',
            ], null, 'A' . $row);

            $row++;
        }

        return $spreadsheet;
    }

    public function writer(Spreadsheet $spreadsheet): Xlsx
    {
        return new Xlsx($spreadsheet);
    }

    public function buildSantanderTxt(PlanillaPagoChofer $planilla): string
    {
        $planilla->load(['reciboLinks.recibo.transportista.defaultPaymentMethod']);

        $validationErrors = [];

        // Validate Company Parameters (Header)
        $companyCuit = SystemParameter::value('company_cuit');
        $cuitClean = preg_replace('/\D/', '', (string) $companyCuit);
        if ($cuitClean === '') {
            $validationErrors[] = "Configuración: El CUIT de la Empresa está vacío.";
        } elseif (strlen($cuitClean) !== 11) {
            $validationErrors[] = "Configuración: El CUIT de la Empresa debe tener 11 dígitos.";
        }

        $agreementVal = SystemParameter::value('santander_agreement_number');
        $agreementClean = preg_replace('/\D/', '', (string) $agreementVal);
        if ($agreementClean === '') {
            $validationErrors[] = "Configuración: El Número de Acuerdo Santander está vacío.";
        } elseif (strlen($agreementClean) > 2) {
            $validationErrors[] = "Configuración: El Número de Acuerdo Santander no puede superar los 2 dígitos.";
        }

        // Validate Details (D)
        foreach ($planilla->reciboLinks as $link) {
            $recibo = $link->recibo;
            if (!$recibo) {
                continue;
            }

            $transportista = $recibo->transportista;
            if (!$transportista) {
                $validationErrors[] = "Recibo #{$recibo->id}: No tiene un chofer asociado.";
                continue;
            }

            $driverName = $transportista->name ?: "Chofer ID #{$transportista->id}";
            $driverErrors = [];

            // 1. Name
            $sanitizedName = $this->sanitizeString($transportista->name);
            if ($sanitizedName === '') {
                $driverErrors[] = "Nombre vacío o inválido";
            }

            // 2. CUIT
            $taxIdClean = preg_replace('/\D/', '', (string) $transportista->tax_id);
            if ($taxIdClean === '') {
                $driverErrors[] = "CUIT/CUIL vacío";
            } elseif (strlen($taxIdClean) !== 11) {
                $driverErrors[] = "CUIT/CUIL debe tener 11 dígitos (tiene " . strlen($taxIdClean) . ")";
            }

            // 3. CBU
            $defaultMethod = $transportista->defaultPaymentMethod;
            $cbu = trim((string) ($defaultMethod ? $defaultMethod->cbu : $transportista->cbu));
            $cbuClean = preg_replace('/\D/', '', $cbu);
            if ($cbuClean === '') {
                $driverErrors[] = "CBU/CVU vacío";
            } elseif (strlen($cbuClean) !== 22) {
                $driverErrors[] = "CBU/CVU debe tener 22 dígitos (tiene " . strlen($cbuClean) . ")";
            }

            // 4. Amount
            $amount = (float) $link->monto_en_planilla;
            if ($amount <= 0) {
                $driverErrors[] = "Importe a pagar debe ser mayor a 0 (monto actual: $" . number_format($amount, 2, ',', '.') . ")";
            }

            // 5. Period
            if (!$recibo->periodo_desde) {
                $driverErrors[] = "Período abonado (fecha desde) vacío";
            }

            if (!empty($driverErrors)) {
                $validationErrors[] = "Chofer: {$driverName} (Recibo #{$recibo->id}) - Faltan datos obligatorios: " . implode(', ', $driverErrors);
            }
        }

        if (!empty($validationErrors)) {
            throw new \InvalidArgumentException(json_encode($validationErrors));
        }

        $cuit = preg_replace('/\D/', '', SystemParameter::value('company_cuit', '30123456789'));
        $agreement = preg_replace('/\D/', '', SystemParameter::value('santander_agreement_number', '01'));
        $cuit = str_pad(substr($cuit, 0, 11), 11, '0', STR_PAD_LEFT);
        $agreement = str_pad(substr($agreement, 0, 2), 2, '0', STR_PAD_LEFT);

        $lines = [];

        // 1. Header (H)
        // Pos 1: H (1)
        // Pos 2-18: CUIT (11) + Fijo 0 (1) + Product 013 (3) + Agreement (2) = 17
        // Pos 19-21: Channel 007 (3)
        // Pos 22-26: Envio 00001 (5)
        // Pos 27-31: Ceros 00000 (5)
        // Pos 32-38: Spaces (7)
        // Pos 39: Validation S (1)
        // Pos 40-650: Spaces (611)
        $header = "H" . $cuit . "0" . "013" . $agreement . "0070000100000" . str_repeat(' ', 7) . "S" . str_repeat(' ', 611);
        $lines[] = $header;

        $totalCents = 0;
        $detailCount = 0;

        // 2. Details (D)
        foreach ($planilla->reciboLinks as $link) {
            $recibo = $link->recibo;
            if (!$recibo) {
                continue;
            }

            $transportista = $recibo->transportista;
            if (!$transportista) {
                continue;
            }

            $detailCount++;

            // Amount in cents
            $amount = (float) $link->monto_en_planilla;
            $cents = (int) round($amount * 100);
            $totalCents += $cents;

            // Retrieve CBU/CVU
            $defaultMethod = $transportista->defaultPaymentMethod;
            $cbu = trim((string) ($defaultMethod ? $defaultMethod->cbu : $transportista->cbu));
            $cbuClean = preg_replace('/\D/', '', $cbu);

            // CBU to 26 digits formatting
            if (strlen($cbuClean) === 22) {
                $block1 = substr($cbuClean, 0, 8);
                $block2 = substr($cbuClean, 8, 14);
                $formattedCbu = "0" . $block1 . "000" . $block2;
            } else {
                $formattedCbu = str_repeat('0', 26);
            }

            // Forma de Pago: 50 = Santander (starts with 072), 52 = SNP Other banks
            $isSantander = str_starts_with($cbuClean, '072');
            $formaPago = $isSantander ? '50' : '52';

            // Email and email flag
            $email = trim((string) $transportista->email);
            $emailFlag = ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) ? 'S' : 'N';
            $emailField = $emailFlag === 'S' ? str_pad(substr($email, 0, 90), 90, ' ', STR_PAD_RIGHT) : str_repeat(' ', 90);

            // CUIT Beneficiario
            $taxIdClean = preg_replace('/\D/', '', (string) $transportista->tax_id);
            $taxIdField = str_pad(substr($taxIdClean, 0, 11), 11, '0', STR_PAD_LEFT);

            // Name
            $sanitizedName = $this->sanitizeString($transportista->name);
            $nameField = str_pad(substr($sanitizedName, 0, 30), 30, ' ', STR_PAD_RIGHT);

            // Beneficiary Code (15 chars)
            $benefCode = str_pad((string) $transportista->id, 15, ' ', STR_PAD_RIGHT);

            // Comprobante Number (15 chars) - Periodo abonado AAAAMM rellenado con ceros a la izquierda
            $periodo = $recibo->periodo_desde ? $recibo->periodo_desde->format('Ym') : date('Ym');
            $compNum = str_pad($periodo, 15, '0', STR_PAD_LEFT);

            // Payment Date
            $paymentDate = $planilla->fecha ? $planilla->fecha->format('Ymd') : date('Ymd');

            // Assemble Detail Line (exact lengths checked)
            $detail = "D"                                                     // 1
                . " "                                                          // 2
                . "0"                                                          // 3 (Pesos)
                . $benefCode                                                   // 4-18 (15)
                . "RC"                                                         // 19-20 (2)
                . $compNum                                                     // 21-35 (15)
                . "0000"                                                       // 36-39 (4)
                . $nameField                                                   // 40-69 (30)
                . str_repeat(' ', 51)                                          // 70-120 (51)
                . "00000"                                                      // 121-125 (5)
                . "   "                                                        // 126-128 (3)
                . $emailFlag                                                   // 129 (1)
                . $emailField                                                  // 130-219 (90)
                . "    "                                                       // 220-223 (4)
                . $taxIdField                                                  // 224-234 (11)
                . str_repeat(' ', 162)                                         // 235-396 (162)
                . "N"                                                          // 397 (1)
                . "0054"                                                       // 398-401 (4)
                . $formattedCbu                                                // 402-427 (26)
                . "00000000"                                                   // 428-435 (8)
                . $paymentDate                                                 // 436-443 (8)
                . str_pad((string) $cents, 15, '0', STR_PAD_LEFT)              // 444-458 (15)
                . $formaPago                                                   // 459-460 (2)
                . "   "                                                        // 461-463 (3)
                . "00000000000"                                                // 464-474 (11)
                . "   "                                                        // 475-477 (3)
                . "00000000000"                                                // 478-488 (11)
                . "   "                                                        // 489-491 (3)
                . "00000000000"                                                // 492-502 (11)
                . "   "                                                        // 503-505 (3)
                . str_repeat('0', 25)                                          // 506-530 (25)
                . " "                                                          // 531 (1)
                . str_repeat('0', 17)                                          // 532-548 (17)
                . str_repeat(' ', 102);                                        // 549-650 (102)

            $lines[] = $detail;
        }

        // 3. Trailer (T)
        // Pos 1: T (1)
        // Pos 2-16: Reserved (15) => ceros
        // Pos 17-31: Total cents (15) => total de todos los detalles
        // Pos 32-38: Details count (7) => cantidad de detalles D
        // Pos 39-650: Reserved (612) => spaces
        $trailer = "T"
            . str_repeat('0', 15)
            . str_pad((string) $totalCents, 15, '0', STR_PAD_LEFT)
            . str_pad((string) $detailCount, 7, '0', STR_PAD_LEFT)
            . str_repeat(' ', 612);
        $lines[] = $trailer;

        return implode("\r\n", $lines) . "\r\n";
    }

    private function sanitizeString(string $string): string
    {
        $replace = [
            'á'=>'a', 'é'=>'e', 'í'=>'i', 'ó'=>'o', 'ú'=>'u',
            'Á'=>'A', 'É'=>'E', 'Í'=>'I', 'Ó'=>'O', 'Ú'=>'U',
            'ñ'=>'n', 'Ñ'=>'N', 'ü'=>'u', 'Ü'=>'U', 'ç'=>'c', 'Ç'=>'C'
        ];
        $string = strtr($string, $replace);
        $string = preg_replace('/[^a-zA-Z0-9\s]/', '', $string);
        return strtoupper(trim($string));
    }
}

