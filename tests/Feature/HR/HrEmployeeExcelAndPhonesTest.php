<?php

namespace Tests\Feature\HR;

use App\Models\HR\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class HrEmployeeExcelAndPhonesTest extends TestCase
{
    use DatabaseTransactions;

    protected $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminUser = User::firstOrCreate(['email' => 'admin_excel_test@example.com'], [
            'name' => 'Admin Excel Test',
            'password' => Hash::make('password'),
            'role' => defined('App\Models\User::ROLE_ADMIN') ? User::ROLE_ADMIN : 'admin',
            'accepted_at' => now(),
        ]);
    }

    /** @test */
    public function admin_can_create_and_update_employee_with_personal_and_work_phones()
    {
        $response = $this->actingAs($this->adminUser)->post(route('rrhh.employees.store'), [
            'first_name' => 'Roberto',
            'last_name' => 'Gómez',
            'file_number' => 'LEG-9901',
            'dni' => '39888777',
            'cuil' => '20-39888777-9',
            'personal_phone' => '+54 9 11 5555-0001',
            'work_phone' => '+54 9 11 4444-0002',
            'personal_email' => 'roberto.gomez@gmail.com',
            'work_email' => 'rgomez@empresa.com',
            'hire_date' => '2026-01-10',
            'status' => 'activo',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('hr_employees', [
            'file_number' => 'LEG-9901',
            'dni' => '39888777',
            'personal_phone' => '+54 9 11 5555-0001',
            'work_phone' => '+54 9 11 4444-0002',
        ]);

        $employee = Employee::where('file_number', 'LEG-9901')->firstOrFail();

        $updateResponse = $this->actingAs($this->adminUser)->put(route('rrhh.employees.update', $employee), [
            'first_name' => 'Roberto Carlos',
            'last_name' => 'Gómez',
            'file_number' => 'LEG-9901',
            'dni' => '39888777',
            'personal_phone' => '+54 9 11 5555-9999',
            'work_phone' => '+54 9 11 4444-8888',
            'hire_date' => '2026-01-10',
            'status' => 'activo',
        ]);

        $updateResponse->assertRedirect();
        $this->assertDatabaseHas('hr_employees', [
            'id' => $employee->id,
            'first_name' => 'Roberto Carlos',
            'personal_phone' => '+54 9 11 5555-9999',
            'work_phone' => '+54 9 11 4444-8888',
        ]);
    }

    /** @test */
    public function admin_can_export_empty_and_full_payroll_excel()
    {
        Employee::create([
            'first_name' => 'Esteban',
            'last_name' => 'Quito',
            'file_number' => 'LEG-100',
            'dni' => '31111222',
            'personal_phone' => '+54 9 11 1111-2222',
            'work_phone' => '+54 9 11 3333-4444',
            'hire_date' => '2025-05-05',
            'status' => 'activo',
        ]);

        // Exportación plantilla vacía
        $responseEmpty = $this->actingAs($this->adminUser)
            ->get(route('rrhh.employees.export', ['mode' => 'empty']));

        $responseEmpty->assertStatus(200);
        $responseEmpty->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        // Exportación nómina completa
        $responsePayroll = $this->actingAs($this->adminUser)
            ->get(route('rrhh.employees.export', ['mode' => 'payroll']));

        $responsePayroll->assertStatus(200);
        $responsePayroll->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    /** @test */
    public function admin_can_preview_and_import_excel_payroll()
    {
        // Crear un archivo Excel en disco temporal
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Cabeceras
        $sheet->setCellValue('A1', 'Número de Legajo');
        $sheet->setCellValue('B1', 'DNI / Documento');
        $sheet->setCellValue('C1', 'Nombre(s)');
        $sheet->setCellValue('D1', 'Apellido(s)');
        $sheet->setCellValue('E1', 'Teléfono Personal');
        $sheet->setCellValue('F1', 'Teléfono Laboral');
        $sheet->setCellValue('G1', 'Fecha de Ingreso (AAAA-MM-DD)');

        // Fila 2
        $sheet->setCellValue('A2', 'LEG-8888');
        $sheet->setCellValue('B2', '40111222');
        $sheet->setCellValue('C2', 'Laura');
        $sheet->setCellValue('D2', 'Fernández');
        $sheet->setCellValue('E2', '+54 9 11 6666-7777');
        $sheet->setCellValue('F2', '+54 9 11 8888-9999');
        $sheet->setCellValue('G2', '2026-03-15');

        $tempPath = tempnam(sys_get_temp_dir(), 'hr_excel_test_') . '.xlsx';
        $writer = new Xlsx($spreadsheet);
        $writer->save($tempPath);

        $uploadedFile = new UploadedFile(
            $tempPath,
            'test_import.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );

        // Previsualizar
        $previewResponse = $this->actingAs($this->adminUser)
            ->postJson(route('rrhh.employees.import-preview'), [
                'excel_file' => $uploadedFile,
            ]);

        $previewResponse->assertStatus(200)
            ->assertJson([
                'total_rows' => 1,
                'create_count' => 1,
                'update_count' => 0,
                'error_count' => 0,
            ]);

        // Importar
        $importResponse = $this->actingAs($this->adminUser)
            ->postJson(route('rrhh.employees.import'), [
                'excel_file' => $uploadedFile,
            ]);

        $importResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'created' => 1,
                'updated' => 0,
            ]);

        $this->assertDatabaseHas('hr_employees', [
            'file_number' => 'LEG-8888',
            'dni' => '40111222',
            'first_name' => 'Laura',
            'last_name' => 'Fernández',
            'personal_phone' => '+54 9 11 6666-7777',
            'work_phone' => '+54 9 11 8888-9999',
        ]);

        if (file_exists($tempPath)) {
            @unlink($tempPath);
        }
    }
}
