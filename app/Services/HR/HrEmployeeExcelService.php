<?php

namespace App\Services\HR;

use App\Models\HR\Agreement;
use App\Models\HR\Branch;
use App\Models\HR\Department;
use App\Models\HR\Employee;
use App\Models\HR\Position;
use App\Services\HR\HrAuditService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class HrEmployeeExcelService
{
    /**
     * Definición de columnas para exportación e importación
     */
    public static function getColumnsDefinition(): array
    {
        return [
            ['key' => 'file_number', 'label' => 'Número de Legajo', 'required' => true],
            ['key' => 'dni', 'label' => 'DNI / Documento', 'required' => true],
            ['key' => 'cuil', 'label' => 'CUIL / CUIT', 'required' => false],
            ['key' => 'first_name', 'label' => 'Nombre(s)', 'required' => true],
            ['key' => 'last_name', 'label' => 'Apellido(s)', 'required' => true],
            ['key' => 'personal_phone', 'label' => 'Teléfono Personal', 'required' => false],
            ['key' => 'work_phone', 'label' => 'Teléfono Laboral', 'required' => false],
            ['key' => 'personal_email', 'label' => 'Correo Personal', 'required' => false],
            ['key' => 'work_email', 'label' => 'Correo Corporativo', 'required' => false],
            ['key' => 'gender', 'label' => 'Género (M/F/X/otro)', 'required' => false],
            ['key' => 'birth_date', 'label' => 'Fecha Nacimiento (AAAA-MM-DD)', 'required' => false],
            ['key' => 'nationality', 'label' => 'Nacionalidad', 'required' => false],
            ['key' => 'marital_status', 'label' => 'Estado Civil', 'required' => false],
            ['key' => 'address', 'label' => 'Domicilio', 'required' => false],
            ['key' => 'city', 'label' => 'Ciudad / Localidad', 'required' => false],
            ['key' => 'province', 'label' => 'Provincia', 'required' => false],
            ['key' => 'postal_code', 'label' => 'Código Postal', 'required' => false],
            ['key' => 'emergency_contact_name', 'label' => 'Contacto Emergencia Nombre', 'required' => false],
            ['key' => 'emergency_contact_phone', 'label' => 'Contacto Emergencia Teléfono', 'required' => false],
            ['key' => 'emergency_contact_relationship', 'label' => 'Contacto Emergencia Vínculo', 'required' => false],
            ['key' => 'department', 'label' => 'Área / Departamento', 'required' => false],
            ['key' => 'position', 'label' => 'Puesto de Trabajo', 'required' => false],
            ['key' => 'branch', 'label' => 'Sucursal / Base', 'required' => false],
            ['key' => 'manager_file_number', 'label' => 'Legajo Responsable Directo', 'required' => false],
            ['key' => 'agreement', 'label' => 'Convenio Colectivo', 'required' => false],
            ['key' => 'hire_date', 'label' => 'Fecha de Ingreso (AAAA-MM-DD)', 'required' => true],
            ['key' => 'vacation_seniority_date', 'label' => 'Antigüedad Vacaciones (AAAA-MM-DD)', 'required' => false],
            ['key' => 'probation_end_date', 'label' => 'Fin Período Prueba (AAAA-MM-DD)', 'required' => false],
            ['key' => 'contract_type', 'label' => 'Tipo Contrato (indeterminado/plazo_fijo/pasantia/eventual/otro)', 'required' => false],
            ['key' => 'status', 'label' => 'Estado (activo/licencia/suspendido/en_onboarding/egresado)', 'required' => false],
            ['key' => 'salary', 'label' => 'Salario Bruto', 'required' => false],
            ['key' => 'notes', 'label' => 'Observaciones Internas', 'required' => false],
        ];
    }

    /**
     * Generar archivo Excel de exportación (vacío o con nómina completa)
     */
    public function export(string $mode = 'payroll'): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Colaboradores');

        $columns = self::getColumnsDefinition();

        // Estilos del encabezado
        $headerStyle = [
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
                'size' => 11,
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '1F2937'], // Gris oscuro / Slate
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => '374151'],
                ],
            ],
        ];

        // Escribir fila de cabeceras
        $colIndex = 1;
        foreach ($columns as $col) {
            $sheet->setCellValueByColumnAndRow($colIndex, 1, $col['label']);
            $colIndex++;
        }
        $sheet->getRowDimension(1)->setRowHeight(32);
        $highestColumn = $sheet->getHighestColumn();
        $sheet->getStyle("A1:{$highestColumn}1")->applyFromArray($headerStyle);

        if ($mode === 'empty') {
            // Fila de ejemplo / plantilla vacía
            $sampleData = [
                'file_number' => 'LEG-0101',
                'dni' => '35123456',
                'cuil' => '20-35123456-8',
                'first_name' => 'Juan Carlos',
                'last_name' => 'Pérez',
                'personal_phone' => '+54 9 11 5555-1234',
                'work_phone' => '+54 9 11 4444-5678',
                'personal_email' => 'juan.perez@email.com',
                'work_email' => 'jperez@empresa.com',
                'gender' => 'M',
                'birth_date' => '1990-05-15',
                'nationality' => 'Argentina',
                'marital_status' => 'casado',
                'address' => 'Av. Corrientes 1234, Piso 4',
                'city' => 'CABA',
                'province' => 'Buenos Aires',
                'postal_code' => '1043',
                'emergency_contact_name' => 'María Pérez',
                'emergency_contact_phone' => '+54 9 11 5555-9999',
                'emergency_contact_relationship' => 'Cónyuge',
                'department' => 'Operaciones',
                'position' => 'Conductor de Carga',
                'branch' => 'Casa Central',
                'manager_file_number' => '',
                'agreement' => 'Camioneros',
                'hire_date' => date('Y-m-d'),
                'vacation_seniority_date' => date('Y-m-d'),
                'probation_end_date' => '',
                'contract_type' => 'indeterminado',
                'status' => 'activo',
                'salary' => '850000.00',
                'notes' => 'Fila de ejemplo (eliminar antes de cargar si no corresponde)',
            ];

            $c = 1;
            foreach ($columns as $col) {
                $sheet->setCellValueByColumnAndRow($c, 2, $sampleData[$col['key']] ?? '');
                $c++;
            }
            $sheet->getStyle("A2:{$highestColumn}2")->getFont()->setItalic(true);
        } else {
            // Nómina completa de colaboradores
            $employees = Employee::with(['department', 'position', 'branch', 'manager', 'agreement'])
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->get();

            $rowIndex = 2;
            foreach ($employees as $emp) {
                $c = 1;
                foreach ($columns as $col) {
                    $val = '';
                    switch ($col['key']) {
                        case 'file_number':
                            $val = $emp->file_number;
                            break;
                        case 'dni':
                            $val = $emp->dni;
                            break;
                        case 'cuil':
                            $val = $emp->cuil ?? '';
                            break;
                        case 'first_name':
                            $val = $emp->first_name;
                            break;
                        case 'last_name':
                            $val = $emp->last_name;
                            break;
                        case 'personal_phone':
                            $val = $emp->personal_phone ?? $emp->phone ?? '';
                            break;
                        case 'work_phone':
                            $val = $emp->work_phone ?? '';
                            break;
                        case 'personal_email':
                            $val = $emp->personal_email ?? '';
                            break;
                        case 'work_email':
                            $val = $emp->work_email ?? '';
                            break;
                        case 'gender':
                            $val = $emp->gender ?? 'otro';
                            break;
                        case 'birth_date':
                            $val = $emp->birth_date ? $emp->birth_date->format('Y-m-d') : '';
                            break;
                        case 'nationality':
                            $val = $emp->nationality ?? '';
                            break;
                        case 'marital_status':
                            $val = $emp->marital_status ?? '';
                            break;
                        case 'address':
                            $val = $emp->address ?? '';
                            break;
                        case 'city':
                            $val = $emp->city ?? '';
                            break;
                        case 'province':
                            $val = $emp->province ?? '';
                            break;
                        case 'postal_code':
                            $val = $emp->postal_code ?? '';
                            break;
                        case 'emergency_contact_name':
                            $val = $emp->emergency_contact_name ?? '';
                            break;
                        case 'emergency_contact_phone':
                            $val = $emp->emergency_contact_phone ?? '';
                            break;
                        case 'emergency_contact_relationship':
                            $val = $emp->emergency_contact_relationship ?? '';
                            break;
                        case 'department':
                            $val = $emp->department ? $emp->department->name : '';
                            break;
                        case 'position':
                            $val = $emp->position ? $emp->position->name : '';
                            break;
                        case 'branch':
                            $val = $emp->branch ? $emp->branch->name : '';
                            break;
                        case 'manager_file_number':
                            $val = $emp->manager ? $emp->manager->file_number : '';
                            break;
                        case 'agreement':
                            $val = $emp->agreement ? $emp->agreement->name : '';
                            break;
                        case 'hire_date':
                            $val = $emp->hire_date ? $emp->hire_date->format('Y-m-d') : '';
                            break;
                        case 'vacation_seniority_date':
                            $val = $emp->vacation_seniority_date ? $emp->vacation_seniority_date->format('Y-m-d') : '';
                            break;
                        case 'probation_end_date':
                            $val = $emp->probation_end_date ? $emp->probation_end_date->format('Y-m-d') : '';
                            break;
                        case 'contract_type':
                            $val = $emp->contract_type ?? 'indeterminado';
                            break;
                        case 'status':
                            $val = $emp->status ?? 'activo';
                            break;
                        case 'salary':
                            $val = $emp->salary ? (string) $emp->salary : '';
                            break;
                        case 'notes':
                            $val = $emp->notes ?? '';
                            break;
                        default:
                            $val = '';
                            break;
                    }

                    $sheet->setCellValueExplicitByColumnAndRow($c, $rowIndex, (string) $val, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $c++;
                }
                $rowIndex++;
            }
        }

        // Auto-ajustar ancho de columnas
        foreach (range('A', $highestColumn) as $colLetter) {
            $sheet->getColumnDimension($colLetter)->setAutoSize(true);
        }

        return $spreadsheet;
    }

    /**
     * Mapear encabezados de fila 1 a las keys correspondientes
     */
    private function mapHeaderColumns(array $headerRow): array
    {
        $map = [];
        $columnsDef = self::getColumnsDefinition();

        foreach ($headerRow as $index => $cellValue) {
            $header = strtolower(trim((string)$cellValue));
            $headerClean = preg_replace('/[^a-z0-9]/', '', $header);

            foreach ($columnsDef as $def) {
                $key = $def['key'];
                $labelClean = preg_replace('/[^a-z0-9]/', '', strtolower($def['label']));

                // Coincidencia exacta
                if ($headerClean === $labelClean || $headerClean === preg_replace('/[^a-z0-9]/', '', strtolower($key))) {
                    $map[$index] = $key;
                    break;
                }

                // Aliases conocidos
                $matchedKey = null;
                if (str_contains($header, 'legajo')) {
                    $matchedKey = 'file_number';
                } elseif (str_contains($header, 'dni') || str_contains($header, 'documento')) {
                    $matchedKey = 'dni';
                } elseif (str_contains($header, 'cuil') || str_contains($header, 'cuit')) {
                    $matchedKey = 'cuil';
                } elseif (str_contains($header, 'nombre') && !str_contains($header, 'contacto')) {
                    $matchedKey = 'first_name';
                } elseif (str_contains($header, 'apellido')) {
                    $matchedKey = 'last_name';
                } elseif (str_contains($header, 'personal') && (str_contains($header, 'tel') || str_contains($header, 'fon'))) {
                    $matchedKey = 'personal_phone';
                } elseif (str_contains($header, 'laboral') && (str_contains($header, 'tel') || str_contains($header, 'fon'))) {
                    $matchedKey = 'work_phone';
                } elseif ((str_contains($header, 'tel') || str_contains($header, 'celular')) && !str_contains($header, 'laboral') && !str_contains($header, 'emergencia')) {
                    $matchedKey = 'personal_phone';
                } elseif (str_contains($header, 'personal') && (str_contains($header, 'mail') || str_contains($header, 'correo'))) {
                    $matchedKey = 'personal_email';
                } elseif ((str_contains($header, 'laboral') || str_contains($header, 'corporativo')) && (str_contains($header, 'mail') || str_contains($header, 'correo'))) {
                    $matchedKey = 'work_email';
                } elseif (str_contains($header, 'gnero') || str_contains($header, 'genero') || $header === 'sexo') {
                    $matchedKey = 'gender';
                } elseif (str_contains($header, 'nacimiento')) {
                    $matchedKey = 'birth_date';
                } elseif (str_contains($header, 'nacionalidad')) {
                    $matchedKey = 'nationality';
                } elseif (str_contains($header, 'estado civil') || str_contains($header, 'civil')) {
                    $matchedKey = 'marital_status';
                } elseif (str_contains($header, 'domicilio') || str_contains($header, 'direcc')) {
                    $matchedKey = 'address';
                } elseif (str_contains($header, 'ciudad') || str_contains($header, 'localidad')) {
                    $matchedKey = 'city';
                } elseif (str_contains($header, 'provincia')) {
                    $matchedKey = 'province';
                } elseif (str_contains($header, 'postal') || $header === 'cp') {
                    $matchedKey = 'postal_code';
                } elseif (str_contains($header, 'emergencia') && str_contains($header, 'nombre')) {
                    $matchedKey = 'emergency_contact_name';
                } elseif (str_contains($header, 'emergencia') && (str_contains($header, 'tel') || str_contains($header, 'fon'))) {
                    $matchedKey = 'emergency_contact_phone';
                } elseif (str_contains($header, 'emergencia') && (str_contains($header, 'vinculo') || str_contains($header, 'parentesco') || str_contains($header, 'vnculo'))) {
                    $matchedKey = 'emergency_contact_relationship';
                } elseif (str_contains($header, 'rea') || str_contains($header, 'area') || str_contains($header, 'departamento')) {
                    $matchedKey = 'department';
                } elseif (str_contains($header, 'puesto') || str_contains($header, 'cargo')) {
                    $matchedKey = 'position';
                } elseif (str_contains($header, 'sucursal') || str_contains($header, 'base')) {
                    $matchedKey = 'branch';
                } elseif (str_contains($header, 'responsable') || str_contains($header, 'lider') || str_contains($header, 'manager')) {
                    $matchedKey = 'manager_file_number';
                } elseif (str_contains($header, 'convenio') || str_contains($header, 'politica') || str_contains($header, 'poltica')) {
                    $matchedKey = 'agreement';
                } elseif (str_contains($header, 'ingreso')) {
                    $matchedKey = 'hire_date';
                } elseif (str_contains($header, 'antig') && str_contains($header, 'vacac')) {
                    $matchedKey = 'vacation_seniority_date';
                } elseif (str_contains($header, 'prueba')) {
                    $matchedKey = 'probation_end_date';
                } elseif (str_contains($header, 'contrato')) {
                    $matchedKey = 'contract_type';
                } elseif (str_contains($header, 'estado')) {
                    $matchedKey = 'status';
                } elseif (str_contains($header, 'salario') || str_contains($header, 'sueldo')) {
                    $matchedKey = 'salary';
                } elseif (str_contains($header, 'observ') || str_contains($header, 'notas')) {
                    $matchedKey = 'notes';
                }

                if ($matchedKey) {
                    $map[$index] = $matchedKey;
                    break;
                }
            }
        }

        return $map;
    }

    /**
     * Previsualización de datos a importar desde Excel
     */
    public function preview(string $filePath): array
    {
        $spreadsheet = IOFactory::load($filePath);
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray(null, true, true, true);

        if (count($rows) < 2) {
            return [
                'total_rows' => 0,
                'create_count' => 0,
                'update_count' => 0,
                'error_count' => 0,
                'rows' => [],
                'error' => 'El archivo Excel no contiene filas de datos.',
            ];
        }

        $headerRow = array_values($rows[1]);
        $columnMap = $this->mapHeaderColumns($headerRow);

        $previewRows = [];
        $createCount = 0;
        $updateCount = 0;
        $errorCount = 0;

        // Cargar registros existentes en memoria para consulta rápida por DNI o Legajo
        $existingDnis = Employee::withTrashed()->pluck('id', 'dni')->toArray();
        $existingFileNumbers = Employee::withTrashed()->pluck('id', 'file_number')->toArray();

        foreach (array_slice($rows, 1, null, true) as $excelRowIndex => $rowValues) {
            $values = array_values($rowValues);

            // Armar data de la fila
            $rowData = [];
            foreach ($columnMap as $idx => $key) {
                $rowData[$key] = isset($values[$idx]) ? trim((string)$values[$idx]) : '';
            }

            // Ignorar filas totalmente vacías
            $nonEmpty = array_filter($rowData, function ($v) {
                return $v !== '';
            });
            if (empty($nonEmpty)) {
                continue;
            }

            $dni = preg_replace('/[^0-9Kk]/', '', $rowData['dni'] ?? '');
            $fileNumber = trim($rowData['file_number'] ?? '');
            $firstName = trim($rowData['first_name'] ?? '');
            $lastName = trim($rowData['last_name'] ?? '');

            $issues = [];

            if (empty($fileNumber)) {
                $issues[] = 'Número de legajo requerido';
            }
            if (empty($dni)) {
                $issues[] = 'DNI requerido';
            }
            if (empty($firstName)) {
                $issues[] = 'Nombre requerido';
            }
            if (empty($lastName)) {
                $issues[] = 'Apellido requerido';
            }

            // Determinar si es actualización o alta
            $isUpdate = false;

            if (!empty($dni) && isset($existingDnis[$dni])) {
                $isUpdate = true;
            } elseif (!empty($fileNumber) && isset($existingFileNumbers[$fileNumber])) {
                $isUpdate = true;
            }

            $action = $isUpdate ? 'update' : 'create';
            $status = empty($issues) ? 'ok' : 'error';

            if ($status === 'ok') {
                if ($isUpdate) {
                    $updateCount++;
                } else {
                    $createCount++;
                }
            } else {
                $errorCount++;
            }

            $previewRows[] = [
                'row_number' => $excelRowIndex,
                'file_number' => $fileNumber ?: '-',
                'dni' => $dni ?: '-',
                'full_name' => ($lastName || $firstName) ? "{$lastName}, {$firstName}" : '-',
                'personal_phone' => $rowData['personal_phone'] ?? '',
                'work_phone' => $rowData['work_phone'] ?? '',
                'personal_email' => $rowData['personal_email'] ?? '',
                'department' => $rowData['department'] ?? '',
                'position' => $rowData['position'] ?? '',
                'action' => $action,
                'action_label' => $isUpdate ? 'Actualizar' : 'Crear',
                'status' => $status,
                'issues' => $issues,
                'data' => $rowData,
            ];
        }

        return [
            'total_rows' => count($previewRows),
            'create_count' => $createCount,
            'update_count' => $updateCount,
            'error_count' => $errorCount,
            'rows' => $previewRows,
        ];
    }

    /**
     * Procesar e importar colaboradores desde el archivo Excel
     */
    public function import(string $filePath): array
    {
        $previewResult = $this->preview($filePath);

        if (isset($previewResult['error'])) {
            return [
                'success' => false,
                'message' => $previewResult['error'],
                'created' => 0,
                'updated' => 0,
                'errors' => 0,
            ];
        }

        // Cache de entidades relacionadas (Departamentos, Puestos, Sucursales, Convenios)
        $departments = Department::all()->keyBy(function ($item) {
            return strtolower(trim($item->name));
        });
        $departmentsByCode = Department::all()->keyBy(function ($item) {
            return strtolower(trim($item->code));
        });

        $positions = Position::all()->keyBy(function ($item) {
            return strtolower(trim($item->name));
        });
        $positionsByCode = Position::all()->keyBy(function ($item) {
            return strtolower(trim($item->code));
        });

        $branches = Branch::all()->keyBy(function ($item) {
            return strtolower(trim($item->name));
        });
        $branchesByCode = Branch::all()->keyBy(function ($item) {
            return strtolower(trim($item->code));
        });

        $agreements = Agreement::all()->keyBy(function ($item) {
            return strtolower(trim($item->name));
        });
        $agreementsByCode = Agreement::all()->keyBy(function ($item) {
            return strtolower(trim($item->code));
        });

        $createdCount = 0;
        $updatedCount = 0;
        $failedCount = 0;

        DB::transaction(function () use (
            $previewResult,
            $departments,
            $departmentsByCode,
            $positions,
            $positionsByCode,
            $branches,
            $branchesByCode,
            $agreements,
            $agreementsByCode,
            &$createdCount,
            &$updatedCount,
            &$failedCount
        ) {
            foreach ($previewResult['rows'] as $item) {
                if ($item['status'] !== 'ok') {
                    $failedCount++;
                    continue;
                }

                $d = $item['data'];
                $dni = preg_replace('/[^0-9Kk]/', '', $d['dni'] ?? '');
                $fileNumber = trim($d['file_number'] ?? '');

                // Buscar empleado existente por DNI o Legajo
                $employee = Employee::where('dni', $dni)
                    ->orWhere('file_number', $fileNumber)
                    ->first();

                // Resolver relaciones
                $deptId = null;
                if (!empty($d['department'])) {
                    $deptKey = strtolower(trim($d['department']));
                    $deptObj = $departmentsByCode->get($deptKey) ?? $departments->get($deptKey);
                    if ($deptObj) {
                        $deptId = $deptObj->id;
                    }
                }

                $posId = null;
                if (!empty($d['position'])) {
                    $posKey = strtolower(trim($d['position']));
                    $posObj = $positionsByCode->get($posKey) ?? $positions->get($posKey);
                    if ($posObj) {
                        $posId = $posObj->id;
                    }
                }

                $branchId = null;
                if (!empty($d['branch'])) {
                    $branchKey = strtolower(trim($d['branch']));
                    $branchObj = $branchesByCode->get($branchKey) ?? $branches->get($branchKey);
                    if ($branchObj) {
                        $branchId = $branchObj->id;
                    }
                }

                $agreementId = null;
                if (!empty($d['agreement'])) {
                    $agKey = strtolower(trim($d['agreement']));
                    $agObj = $agreementsByCode->get($agKey) ?? $agreements->get($agKey);
                    if ($agObj) {
                        $agreementId = $agObj->id;
                    }
                }

                $managerId = null;
                if (!empty($d['manager_file_number'])) {
                    $mgrKey = trim($d['manager_file_number']);
                    $mgrObj = Employee::where('file_number', $mgrKey)->orWhere('dni', $mgrKey)->first();
                    if ($mgrObj) {
                        $managerId = $mgrObj->id;
                    }
                }

                // Parsear Fechas
                $parseDate = function ($val) {
                    if (empty($val)) return null;
                    try {
                        return Carbon::parse($val)->format('Y-m-d');
                    } catch (\Exception $e) {
                        return null;
                    }
                };

                $hireDate = $parseDate($d['hire_date'] ?? null) ?? date('Y-m-d');
                $birthDate = $parseDate($d['birth_date'] ?? null);
                $vacationSeniorityDate = $parseDate($d['vacation_seniority_date'] ?? null);
                $probationEndDate = $parseDate($d['probation_end_date'] ?? null);

                // Normalizar Enums
                $gender = strtolower(trim($d['gender'] ?? 'otro'));
                if (!in_array($gender, ['m', 'f', 'x', 'otro'])) {
                    $gender = 'otro';
                }

                $contractType = strtolower(trim($d['contract_type'] ?? 'indeterminado'));
                if (!in_array($contractType, ['indeterminado', 'plazo_fijo', 'pasantia', 'eventual', 'otro'])) {
                    $contractType = 'indeterminado';
                }

                $status = strtolower(trim($d['status'] ?? 'activo'));
                if (!in_array($status, ['activo', 'licencia', 'suspendido', 'en_onboarding', 'egresado'])) {
                    $status = 'activo';
                }

                $salary = !empty($d['salary']) && is_numeric($d['salary']) ? (float)$d['salary'] : null;

                $dataToSave = [
                    'file_number' => $fileNumber,
                    'first_name' => trim($d['first_name']),
                    'last_name' => trim($d['last_name']),
                    'dni' => $dni,
                    'cuil' => !empty($d['cuil']) ? trim($d['cuil']) : null,
                    'gender' => strtoupper($gender) === 'M' ? 'M' : (strtoupper($gender) === 'F' ? 'F' : (strtoupper($gender) === 'X' ? 'X' : 'otro')),
                    'birth_date' => $birthDate,
                    'nationality' => !empty($d['nationality']) ? trim($d['nationality']) : 'Argentina',
                    'marital_status' => !empty($d['marital_status']) ? trim($d['marital_status']) : null,
                    'phone' => !empty($d['personal_phone']) ? trim($d['personal_phone']) : null,
                    'personal_phone' => !empty($d['personal_phone']) ? trim($d['personal_phone']) : null,
                    'work_phone' => !empty($d['work_phone']) ? trim($d['work_phone']) : null,
                    'personal_email' => !empty($d['personal_email']) ? trim($d['personal_email']) : null,
                    'work_email' => !empty($d['work_email']) ? trim($d['work_email']) : null,
                    'address' => !empty($d['address']) ? trim($d['address']) : null,
                    'city' => !empty($d['city']) ? trim($d['city']) : null,
                    'province' => !empty($d['province']) ? trim($d['province']) : null,
                    'postal_code' => !empty($d['postal_code']) ? trim($d['postal_code']) : null,
                    'emergency_contact_name' => !empty($d['emergency_contact_name']) ? trim($d['emergency_contact_name']) : null,
                    'emergency_contact_phone' => !empty($d['emergency_contact_phone']) ? trim($d['emergency_contact_phone']) : null,
                    'emergency_contact_relationship' => !empty($d['emergency_contact_relationship']) ? trim($d['emergency_contact_relationship']) : null,
                    'department_id' => $deptId,
                    'position_id' => $posId,
                    'branch_id' => $branchId,
                    'agreement_id' => $agreementId,
                    'manager_id' => $managerId,
                    'hire_date' => $hireDate,
                    'vacation_seniority_date' => $vacationSeniorityDate,
                    'probation_end_date' => $probationEndDate,
                    'contract_type' => $contractType,
                    'status' => $status,
                    'salary' => $salary,
                    'notes' => !empty($d['notes']) ? trim($d['notes']) : null,
                ];

                if ($employee) {
                    $oldValues = $employee->toArray();
                    $updateFiltered = array_filter($dataToSave, function ($v) {
                        return !is_null($v);
                    });
                    $employee->update($updateFiltered);
                    $updatedCount++;

                    HrAuditService::log(
                        'employee_imported_update',
                        'Employee',
                        $employee->id,
                        $oldValues,
                        $employee->fresh()->toArray(),
                        "Actualización de datos vía importación Excel de nómina ({$employee->full_name})"
                    );
                } else {
                    $newEmp = Employee::create($dataToSave);
                    $createdCount++;

                    HrAuditService::log(
                        'employee_imported_create',
                        'Employee',
                        $newEmp->id,
                        null,
                        $newEmp->toArray(),
                        "Alta de colaborador vía importación Excel de nómina ({$newEmp->full_name})"
                    );
                }
            }
        });

        return [
            'success' => true,
            'message' => "Proceso completado exitosamente: {$createdCount} colaboradores creados, {$updatedCount} actualizados.",
            'created' => $createdCount,
            'updated' => $updatedCount,
            'errors' => $failedCount,
        ];
    }
}
