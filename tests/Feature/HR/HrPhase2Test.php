<?php

namespace Tests\Feature\HR;

use App\Models\HR\Agreement;
use App\Models\HR\Branch;
use App\Models\HR\Department;
use App\Models\HR\Employee;
use App\Models\HR\EmployeeFile;
use App\Models\HR\HrAuditLog;
use App\Models\HR\Position;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class HrPhase2Test extends TestCase
{
    use DatabaseTransactions;

    protected $adminUser;
    protected $regularUser;
    protected $employeeUser;

    protected function setUp(): void
    {
        parent::setUp();

        // Storage fakes
        Storage::fake('local');
        Storage::fake('public');

        // Limpiar registros residuales en la base de datos de pruebas aislada
        DB::table('hr_employee_files')->delete();
        DB::table('hr_audit_logs')->delete();
        DB::table('hr_employees')->delete();
        DB::table('hr_positions')->delete();
        DB::table('hr_departments')->delete();
        DB::table('hr_branches')->delete();
        DB::table('hr_agreements')->delete();

        $this->adminUser = User::firstOrCreate(['email' => 'admin_phase2@test.com'], [
            'name' => 'Admin Phase2',
            'password' => Hash::make('password'),
            'role' => User::ROLE_ADMIN,
            'accepted_at' => now(),
        ]);

        $this->regularUser = User::firstOrCreate(['email' => 'regular_phase2@test.com'], [
            'name' => 'Regular Phase2',
            'password' => Hash::make('password'),
            'role' => User::ROLE_USER,
            'accepted_at' => now(),
        ]);

        $this->employeeUser = User::firstOrCreate(['email' => 'emp_user_phase2@test.com'], [
            'name' => 'Employee User Phase2',
            'password' => Hash::make('password'),
            'role' => User::ROLE_USER,
            'accepted_at' => now(),
        ]);
    }

    /** @test */
    public function test_01_admin_can_access_employees_list()
    {
        $response = $this->actingAs($this->adminUser)->get(route('rrhh.employees.index'));
        $response->assertStatus(200);
        $response->assertSee('Gestión de Colaboradores');
    }

    /** @test */
    public function test_02_regular_user_cannot_access_employees_list()
    {
        $response = $this->actingAs($this->regularUser)->get(route('rrhh.employees.index'));
        $response->assertStatus(403);
    }

    /** @test */
    public function test_03_active_employee_without_admin_role_cannot_access_employees_list()
    {
        Employee::firstOrCreate(['user_id' => $this->employeeUser->id], [
            'file_number' => 'LEG-P2-001',
            'first_name' => 'Esteban',
            'last_name' => 'Quito',
            'dni' => '10203040',
            'hire_date' => now()->subYears(1)->toDateString(),
            'status' => 'activo',
        ]);

        $response = $this->actingAs($this->employeeUser)->get(route('rrhh.employees.index'));
        $response->assertStatus(403);
    }

    /** @test */
    public function test_04_valid_employee_creation_stores_record_and_audit()
    {
        $dept = Department::create(['name' => 'Operaciones', 'code' => 'OPS-01']);
        $pos = Position::create(['name' => 'Conductor', 'code' => 'COND-01', 'department_id' => $dept->id]);
        $branch = Branch::create(['name' => 'Base Rosario', 'code' => 'ROS-01']);

        $payload = [
            'first_name' => 'Marcos',
            'last_name' => 'Gallardo',
            'dni' => '35123456',
            'cuil' => '20-35123456-8',
            'file_number' => 'LEG-NEW-01',
            'hire_date' => '2026-01-15',
            'status' => 'activo',
            'department_id' => $dept->id,
            'position_id' => $pos->id,
            'branch_id' => $branch->id,
            'phone' => '+54 9 341 5556677',
            'personal_email' => 'marcos@test.com',
        ];

        $response = $this->actingAs($this->adminUser)->post(route('rrhh.employees.store'), $payload);
        $response->assertRedirect();

        $this->assertDatabaseHas('hr_employees', [
            'dni' => '35123456',
            'file_number' => 'LEG-NEW-01',
            'first_name' => 'Marcos',
            'last_name' => 'Gallardo',
        ]);

        $this->assertDatabaseHas('hr_audit_logs', [
            'action' => 'employee_created',
            'entity_type' => 'Employee',
        ]);
    }

    /** @test */
    public function test_05_employee_creation_fails_when_required_fields_are_missing()
    {
        $response = $this->actingAs($this->adminUser)->post(route('rrhh.employees.store'), []);
        $response->assertSessionHasErrors(['first_name', 'last_name', 'dni', 'file_number', 'hire_date', 'status']);
    }

    /** @test */
    public function test_06_duplicate_dni_is_rejected()
    {
        Employee::create([
            'first_name' => 'Lucas',
            'last_name' => 'Britez',
            'dni' => '40000001',
            'file_number' => 'LEG-DNI-01',
            'hire_date' => '2025-05-01',
            'status' => 'activo',
        ]);

        $payload = [
            'first_name' => 'Lucas Duplicado',
            'last_name' => 'Britez',
            'dni' => '40000001', // DNI duplicado
            'file_number' => 'LEG-DNI-02',
            'hire_date' => '2026-02-01',
            'status' => 'activo',
        ];

        $response = $this->actingAs($this->adminUser)->post(route('rrhh.employees.store'), $payload);
        $response->assertSessionHasErrors(['dni']);
    }

    /** @test */
    public function test_07_duplicate_file_number_is_rejected()
    {
        Employee::create([
            'first_name' => 'Ana',
            'last_name' => 'Suarez',
            'dni' => '40000002',
            'file_number' => 'LEG-DUP-01',
            'hire_date' => '2025-05-01',
            'status' => 'activo',
        ]);

        $payload = [
            'first_name' => 'Carla',
            'last_name' => 'Suarez',
            'dni' => '40000003',
            'file_number' => 'LEG-DUP-01', // Legajo duplicado
            'hire_date' => '2026-02-01',
            'status' => 'activo',
        ];

        $response = $this->actingAs($this->adminUser)->post(route('rrhh.employees.store'), $payload);
        $response->assertSessionHasErrors(['file_number']);
    }

    /** @test */
    public function test_08_user_cannot_be_linked_to_two_different_employees()
    {
        $sharedUser = User::firstOrCreate(['email' => 'shared_user@test.com'], [
            'name' => 'Shared User',
            'password' => Hash::make('password'),
            'role' => User::ROLE_USER,
            'accepted_at' => now(),
        ]);

        Employee::create([
            'user_id' => $sharedUser->id,
            'first_name' => 'Primero',
            'last_name' => 'Empleado',
            'dni' => '41000001',
            'file_number' => 'LEG-USER-01',
            'hire_date' => '2025-01-01',
            'status' => 'activo',
        ]);

        $payload = [
            'user_id' => $sharedUser->id, // Intento de vincular el mismo usuario a un segundo empleado
            'first_name' => 'Segundo',
            'last_name' => 'Empleado',
            'dni' => '41000002',
            'file_number' => 'LEG-USER-02',
            'hire_date' => '2026-01-01',
            'status' => 'activo',
        ];

        $response = $this->actingAs($this->adminUser)->post(route('rrhh.employees.store'), $payload);
        $response->assertSessionHasErrors(['user_id']);
    }

    /** @test */
    public function test_09_termination_date_before_hire_date_is_rejected()
    {
        $payload = [
            'first_name' => 'Invalido',
            'last_name' => 'Fechas',
            'dni' => '42000001',
            'file_number' => 'LEG-DATE-01',
            'hire_date' => '2026-05-10',
            'termination_date' => '2026-01-01', // Anterior a la fecha de ingreso
            'status' => 'egresado',
        ];

        $response = $this->actingAs($this->adminUser)->post(route('rrhh.employees.store'), $payload);
        $response->assertSessionHasErrors(['termination_date']);
    }

    /** @test */
    public function test_10_employee_can_be_edited_and_updated()
    {
        $employee = Employee::create([
            'first_name' => 'Diego',
            'last_name' => 'Maradona',
            'dni' => '43000001',
            'file_number' => 'LEG-EDIT-01',
            'hire_date' => '2025-01-01',
            'status' => 'activo',
            'phone' => '111111',
        ]);

        $updatePayload = [
            'first_name' => 'Diego Armando',
            'last_name' => 'Maradona',
            'dni' => '43000001',
            'file_number' => 'LEG-EDIT-01',
            'hire_date' => '2025-01-01',
            'status' => 'activo',
            'phone' => '999999',
        ];

        $response = $this->actingAs($this->adminUser)->put(route('rrhh.employees.update', $employee), $updatePayload);
        $response->assertRedirect(route('rrhh.employees.show', $employee));

        $this->assertDatabaseHas('hr_employees', [
            'id' => $employee->id,
            'first_name' => 'Diego Armando',
            'phone' => '999999',
        ]);

        $this->assertDatabaseHas('hr_audit_logs', [
            'action' => 'employee_updated',
            'entity_id' => $employee->id,
        ]);
    }

    /** @test */
    public function test_11_employee_status_can_be_updated()
    {
        $employee = Employee::create([
            'first_name' => 'Valeria',
            'last_name' => 'Rios',
            'dni' => '44000001',
            'file_number' => 'LEG-STAT-01',
            'hire_date' => '2025-01-01',
            'status' => 'activo',
        ]);

        $response = $this->actingAs($this->adminUser)->post(route('rrhh.employees.update-status', $employee), [
            'status' => 'licencia',
        ]);

        $response->assertRedirect();
        $this->assertEquals('licencia', $employee->fresh()->status);

        $this->assertDatabaseHas('hr_audit_logs', [
            'action' => 'employee_status_changed',
            'entity_id' => $employee->id,
        ]);
    }

    /** @test */
    public function test_12_employee_termination_marks_as_egresado_without_physical_deletion()
    {
        $employee = Employee::create([
            'first_name' => 'Javier',
            'last_name' => 'Pastore',
            'dni' => '45000001',
            'file_number' => 'LEG-TERM-01',
            'hire_date' => '2024-01-01',
            'status' => 'activo',
        ]);

        $response = $this->actingAs($this->adminUser)->post(route('rrhh.employees.terminate', $employee), [
            'termination_date' => '2026-09-01',
            'termination_reason' => 'Renuncia por motivos personales',
        ]);

        $response->assertRedirect(route('rrhh.employees.show', $employee));

        $fresh = $employee->fresh();
        $this->assertEquals('egresado', $fresh->status);
        $this->assertEquals('2026-09-01', $fresh->termination_date->toDateString());
        $this->assertEquals('Renuncia por motivos personales', $fresh->termination_reason);

        // Comprobación de que no se eliminó físicamente
        $this->assertDatabaseHas('hr_employees', ['id' => $employee->id]);
    }

    /** @test */
    public function test_13_department_with_employees_cannot_be_physically_deleted()
    {
        $dept = Department::create(['name' => 'Logística', 'code' => 'LOG-DEL']);
        $employee = Employee::create([
            'department_id' => $dept->id,
            'first_name' => 'Ramiro',
            'last_name' => 'Perez',
            'dni' => '46000001',
            'file_number' => 'LEG-DEPT-DEL',
            'hire_date' => '2025-01-01',
            'status' => 'activo',
        ]);

        $response = $this->actingAs($this->adminUser)->delete(route('rrhh.departments.destroy', $dept));
        $response->assertRedirect();
        $response->assertSessionHas('error');

        // El área sigue existiendo
        $this->assertDatabaseHas('hr_departments', ['id' => $dept->id]);
    }

    /** @test */
    public function test_14_position_with_employees_cannot_be_physically_deleted()
    {
        $pos = Position::create(['name' => 'Mecánico', 'code' => 'MEC-DEL']);
        $employee = Employee::create([
            'position_id' => $pos->id,
            'first_name' => 'Gonzalo',
            'last_name' => 'Higuain',
            'dni' => '47000001',
            'file_number' => 'LEG-POS-DEL',
            'hire_date' => '2025-01-01',
            'status' => 'activo',
        ]);

        $response = $this->actingAs($this->adminUser)->delete(route('rrhh.positions.destroy', $pos));
        $response->assertRedirect();
        $response->assertSessionHas('error');

        $this->assertDatabaseHas('hr_positions', ['id' => $pos->id]);
    }

    /** @test */
    public function test_15_branch_with_employees_cannot_be_physically_deleted()
    {
        $branch = Branch::create(['name' => 'Base Córdoba', 'code' => 'CBA-DEL']);
        $employee = Employee::create([
            'branch_id' => $branch->id,
            'first_name' => 'Nahuel',
            'last_name' => 'Molina',
            'dni' => '48000001',
            'file_number' => 'LEG-BR-DEL',
            'hire_date' => '2025-01-01',
            'status' => 'activo',
        ]);

        $response = $this->actingAs($this->adminUser)->delete(route('rrhh.branches.destroy', $branch));
        $response->assertRedirect();
        $response->assertSessionHas('error');

        $this->assertDatabaseHas('hr_branches', ['id' => $branch->id]);
    }

    /** @test */
    public function test_16_agreement_with_employees_cannot_be_physically_deleted()
    {
        $agreement = Agreement::create(['name' => 'Convenio Mecánicos', 'code' => 'CCT-MEC']);
        $employee = Employee::create([
            'agreement_id' => $agreement->id,
            'first_name' => 'Cristian',
            'last_name' => 'Romero',
            'dni' => '49000001',
            'file_number' => 'LEG-AG-DEL',
            'hire_date' => '2025-01-01',
            'status' => 'activo',
        ]);

        $response = $this->actingAs($this->adminUser)->delete(route('rrhh.agreements.destroy', $agreement));
        $response->assertRedirect();
        $response->assertSessionHas('error');

        $this->assertDatabaseHas('hr_agreements', ['id' => $agreement->id]);
    }

    /** @test */
    public function test_17_valid_document_upload_into_digital_file_stores_in_private_disk()
    {
        $employee = Employee::create([
            'first_name' => 'Lionel',
            'last_name' => 'Messi',
            'dni' => '50000001',
            'file_number' => 'LEG-DOC-01',
            'hire_date' => '2024-01-01',
            'status' => 'activo',
        ]);

        $file = UploadedFile::fake()->createWithContent('apto_medico.pdf', "%PDF-1.4\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF");

        $payload = [
            'document' => $file,
            'category' => 'apto_medico',
            'title' => 'Apto Médico Periódico 2026',
            'issue_date' => '2026-09-01',
            'expiration_date' => '2027-09-01',
            'notes' => 'Apto sin observaciones',
        ];

        $response = $this->actingAs($this->adminUser)->post(route('rrhh.employees.files.store', $employee), $payload);
        $response->assertRedirect();

        $this->assertDatabaseHas('hr_employee_files', [
            'employee_id' => $employee->id,
            'category' => 'apto_medico',
            'title' => 'Apto Médico Periódico 2026',
            'file_name' => 'apto_medico.pdf',
            'is_active' => true,
        ]);

        $dbFile = EmployeeFile::where('employee_id', $employee->id)->first();
        $this->assertNotNull($dbFile);

        // Verificar que se guardó físicamente en Storage::disk('local')
        Storage::disk('local')->assertExists($dbFile->file_path);

        // Verificar que el nombre físico es anónimo/hasheado y no contiene datos personales
        $this->assertStringNotContainsString('50000001', $dbFile->file_path);
        $this->assertStringNotContainsString('Messi', $dbFile->file_path);
    }

    /** @test */
    public function test_18_invalid_file_extension_is_rejected()
    {
        $employee = Employee::create([
            'first_name' => 'Rodrigo',
            'last_name' => 'De Paul',
            'dni' => '51000001',
            'file_number' => 'LEG-DOC-02',
            'hire_date' => '2024-01-01',
            'status' => 'activo',
        ]);

        // Formato ejecutable peligroso
        $file = UploadedFile::fake()->create('malicious.exe', 100, 'application/x-msdownload');

        $payload = [
            'document' => $file,
            'category' => 'otros',
            'title' => 'Archivo no permitido',
        ];

        $response = $this->actingAs($this->adminUser)->post(route('rrhh.employees.files.store', $employee), $payload);
        $response->assertSessionHasErrors(['document']);
    }

    /** @test */
    public function test_19_file_exceeding_size_limit_is_rejected()
    {
        $employee = Employee::create([
            'first_name' => 'Enzo',
            'last_name' => 'Fernandez',
            'dni' => '52000001',
            'file_number' => 'LEG-DOC-03',
            'hire_date' => '2024-01-01',
            'status' => 'activo',
        ]);

        // Archivo de 15 MB (excede el límite de 10 MB)
        $file = UploadedFile::fake()->create('heavy.pdf', 15360, 'application/pdf');

        $payload = [
            'document' => $file,
            'category' => 'otros',
            'title' => 'Archivo muy pesado',
        ];

        $response = $this->actingAs($this->adminUser)->post(route('rrhh.employees.files.store', $employee), $payload);
        $response->assertSessionHasErrors(['document']);
    }

    /** @test */
    public function test_20_private_document_is_not_stored_in_public_disk()
    {
        $employee = Employee::create([
            'first_name' => 'Julian',
            'last_name' => 'Alvarez',
            'dni' => '53000001',
            'file_number' => 'LEG-DOC-04',
            'hire_date' => '2024-01-01',
            'status' => 'activo',
        ]);

        $file = UploadedFile::fake()->image('dni_frente.png');

        $this->actingAs($this->adminUser)->post(route('rrhh.employees.files.store', $employee), [
            'document' => $file,
            'category' => 'dni_documentos',
            'title' => 'DNI Frente',
        ]);

        $dbFile = EmployeeFile::where('employee_id', $employee->id)->first();
        $this->assertNotNull($dbFile);

        // No debe existir en el disco público
        Storage::disk('public')->assertMissing($dbFile->file_path);
        // Debe existir exclusivamente en el disco local privado
        Storage::disk('local')->assertExists($dbFile->file_path);
    }

    /** @test */
    public function test_21_admin_can_download_and_preview_employee_file_safely()
    {
        $employee = Employee::create([
            'first_name' => 'Lautaro',
            'last_name' => 'Martinez',
            'dni' => '54000001',
            'file_number' => 'LEG-DOC-05',
            'hire_date' => '2024-01-01',
            'status' => 'activo',
        ]);

        $file = UploadedFile::fake()->createWithContent('contrato.pdf', "%PDF-1.4\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF");
        $this->actingAs($this->adminUser)->post(route('rrhh.employees.files.store', $employee), [
            'document' => $file,
            'category' => 'contratos',
            'title' => 'Contrato de Trabajo',
        ]);

        $dbFile = EmployeeFile::where('employee_id', $employee->id)->first();
        $this->assertNotNull($dbFile);

        // Descarga
        $downloadResponse = $this->actingAs($this->adminUser)->get(route('rrhh.employees.files.download', [$employee, $dbFile]));
        $downloadResponse->assertStatus(200);
        $downloadResponse->assertHeader('content-disposition');

        // Previsualización inline
        $previewResponse = $this->actingAs($this->adminUser)->get(route('rrhh.employees.files.preview', [$employee, $dbFile]));
        $previewResponse->assertStatus(200);
        $previewResponse->assertHeader('content-type', 'application/pdf');
    }

    /** @test */
    public function test_22_unauthorized_user_cannot_download_employee_file()
    {
        $employee = Employee::create([
            'first_name' => 'Alexis',
            'last_name' => 'Mac Allister',
            'dni' => '55000001',
            'file_number' => 'LEG-DOC-06',
            'hire_date' => '2024-01-01',
            'status' => 'activo',
        ]);

        $file = UploadedFile::fake()->createWithContent('evaluacion.pdf', "%PDF-1.4\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF");
        $this->actingAs($this->adminUser)->post(route('rrhh.employees.files.store', $employee), [
            'document' => $file,
            'category' => 'capacitaciones',
            'title' => 'Evaluación de Desempeño',
        ]);

        $dbFile = EmployeeFile::where('employee_id', $employee->id)->first();
        $this->assertNotNull($dbFile);

        // Intento de descarga por usuario no autorizado -> 403
        $response = $this->actingAs($this->regularUser)->get(route('rrhh.employees.files.download', [$employee, $dbFile]));
        $response->assertStatus(403);
    }

    /** @test */
    public function test_23_document_upload_and_download_are_logged_in_audit_table()
    {
        $employee = Employee::create([
            'first_name' => 'Emiliano',
            'last_name' => 'Martinez',
            'dni' => '56000001',
            'file_number' => 'LEG-DOC-07',
            'hire_date' => '2024-01-01',
            'status' => 'activo',
        ]);

        $file = UploadedFile::fake()->createWithContent('licencia_conducir.pdf', "%PDF-1.4\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF");
        $this->actingAs($this->adminUser)->post(route('rrhh.employees.files.store', $employee), [
            'document' => $file,
            'category' => 'dni_documentos',
            'title' => 'Licencia Nacional de Conducir',
        ]);

        $dbFile = EmployeeFile::where('employee_id', $employee->id)->first();
        $this->assertNotNull($dbFile);

        // Verificar log de subida
        $this->assertDatabaseHas('hr_audit_logs', [
            'action' => 'document_upload',
            'entity_type' => 'EmployeeFile',
            'entity_id' => $dbFile->id,
        ]);

        // Ejecutar descarga
        $this->actingAs($this->adminUser)->get(route('rrhh.employees.files.download', [$employee, $dbFile]));

        // Verificar log de descarga
        $this->assertDatabaseHas('hr_audit_logs', [
            'action' => 'document_download',
            'entity_type' => 'EmployeeFile',
            'entity_id' => $dbFile->id,
        ]);
    }

    /** @test */
    public function test_24_existing_core_erp_routes_remain_fully_functional()
    {
        $responseHome = $this->actingAs($this->adminUser)->get(route('dashboard'));
        $responseHome->assertStatus(200);

        $responseSales = $this->actingAs($this->adminUser)->get('/documents?scope=sale');
        $responseSales->assertStatus(200);

        $responsePurchases = $this->actingAs($this->adminUser)->get('/documents?scope=purchase');
        $responsePurchases->assertStatus(200);
    }

    /** @test */
    public function test_25_employee_file_scoping_prevents_accessing_file_of_another_employee()
    {
        $empA = Employee::create([
            'first_name' => 'Empleado',
            'last_name' => 'Alfa',
            'dni' => '60000001',
            'file_number' => 'LEG-SCOPE-A',
            'hire_date' => '2025-01-01',
            'status' => 'activo',
        ]);

        $empB = Employee::create([
            'first_name' => 'Empleado',
            'last_name' => 'Beta',
            'dni' => '60000002',
            'file_number' => 'LEG-SCOPE-B',
            'hire_date' => '2025-01-01',
            'status' => 'activo',
        ]);

        $fileB = UploadedFile::fake()->createWithContent('documento_b.pdf', "%PDF-1.4\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF");
        $this->actingAs($this->adminUser)->post(route('rrhh.employees.files.store', $empB), [
            'document' => $fileB,
            'category' => 'contratos',
            'title' => 'Contrato Confidencial Empleado B',
        ]);

        $dbFileB = EmployeeFile::where('employee_id', $empB->id)->first();
        $this->assertNotNull($dbFileB);

        // 1. Intento de descarga usando la URL del empleado A con el ID de archivo de B -> debe responder 404
        $respDownload = $this->actingAs($this->adminUser)->get(route('rrhh.employees.files.download', ['employee' => $empA, 'file' => $dbFileB]));
        $respDownload->assertStatus(404);

        // 2. Intento de previsualización usando la URL del empleado A con el archivo de B -> 404
        $respPreview = $this->actingAs($this->adminUser)->get(route('rrhh.employees.files.preview', ['employee' => $empA, 'file' => $dbFileB]));
        $respPreview->assertStatus(404);

        // 3. Intento de anulación usando la URL del empleado A con el archivo de B -> 404
        $respVoid = $this->actingAs($this->adminUser)->post(route('rrhh.employees.files.void', ['employee' => $empA, 'file' => $dbFileB]), [
            'reason' => 'Intento malicioso de anulación cruzada',
        ]);
        $respVoid->assertStatus(404);

        // Confirmar que NO se registró descarga en auditoría
        $this->assertDatabaseMissing('hr_audit_logs', [
            'action' => 'document_download',
            'entity_id' => $dbFileB->id,
            'description' => "Descarga de documento '{$dbFileB->title}' de {$empA->full_name}",
        ]);
    }

    /** @test */
    public function test_26_employee_soft_delete_preserves_db_record_digital_files_physical_storage_and_audit()
    {
        $employee = Employee::create([
            'first_name' => 'Carlos',
            'last_name' => 'Tevez',
            'dni' => '61000001',
            'file_number' => 'LEG-SOFT-DEL',
            'hire_date' => '2024-01-01',
            'status' => 'activo',
        ]);

        $file = UploadedFile::fake()->createWithContent('titulo_secundario.pdf', "%PDF-1.4\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF");
        $this->actingAs($this->adminUser)->post(route('rrhh.employees.files.store', $employee), [
            'document' => $file,
            'category' => 'certificados',
            'title' => 'Título Secundario',
        ]);

        $dbFile = EmployeeFile::where('employee_id', $employee->id)->first();
        $this->assertNotNull($dbFile);
        $physicalPath = $dbFile->file_path;

        // Ejecutar DELETE
        $response = $this->actingAs($this->adminUser)->delete(route('rrhh.employees.destroy', $employee));
        $response->assertRedirect(route('rrhh.employees.index'));

        // 1. Confirmar que el registro continúa en hr_employees pero con deleted_at (Soft Delete)
        $this->assertDatabaseHas('hr_employees', [
            'id' => $employee->id,
            'dni' => '61000001',
        ]);
        $trashedEmployee = Employee::withTrashed()->find($employee->id);
        $this->assertNotNull($trashedEmployee->deleted_at);

        // 2. Confirmar que los documentos continúan en hr_employee_files
        $this->assertDatabaseHas('hr_employee_files', [
            'id' => $dbFile->id,
            'employee_id' => $employee->id,
        ]);

        // 3. Confirmar que el archivo físico continúa almacenado en el disco privado
        Storage::disk('local')->assertExists($physicalPath);

        // 4. Confirmar que existe auditoría de baja lógica / archivo
        $this->assertDatabaseHas('hr_audit_logs', [
            'action' => 'employee_archived',
            'entity_type' => 'Employee',
            'entity_id' => $employee->id,
        ]);
    }

    /** @test */
    public function test_27_path_traversal_and_safe_storage_naming()
    {
        $employee = Employee::create([
            'first_name' => 'Angel',
            'last_name' => 'Di Maria',
            'dni' => '62000001',
            'file_number' => 'LEG-TRAV-01',
            'hire_date' => '2024-01-01',
            'status' => 'activo',
        ]);

        // Simular intento de path traversal en el nombre del archivo
        $traversalName = '../../../../etc/passwd.pdf';
        $file = UploadedFile::fake()->createWithContent($traversalName, "%PDF-1.4\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF");

        $response = $this->actingAs($this->adminUser)->post(route('rrhh.employees.files.store', $employee), [
            'document' => $file,
            'category' => 'otros',
            'title' => 'Documento con Nombre Manipulado',
        ]);
        $response->assertRedirect();

        $dbFile = EmployeeFile::where('employee_id', $employee->id)->first();
        $this->assertNotNull($dbFile);

        // Confirmar que la ruta física no contiene .. y sigue la convención aislada
        $this->assertStringNotContainsString('..', $dbFile->file_path);
        $this->assertStringStartsWith('hr/employee_files/' . $employee->id . '/', $dbFile->file_path);

        // Confirmar descarga segura con slug
        $downloadResponse = $this->actingAs($this->adminUser)->get(route('rrhh.employees.files.download', [$employee, $dbFile]));
        $downloadResponse->assertStatus(200);
        $downloadResponse->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    /** @test */
    public function test_28_real_mime_validation_rejects_mismatched_and_dangerous_content()
    {
        $employee = Employee::create([
            'first_name' => 'Franco',
            'last_name' => 'Armani',
            'dni' => '63000001',
            'file_number' => 'LEG-MIME-01',
            'hire_date' => '2024-01-01',
            'status' => 'activo',
        ]);

        // 1. Archivo PHP renombrado como .pdf (MIME real text/x-php)
        $fakePhpAsPdf = UploadedFile::fake()->create('malicioso.pdf', 10, 'text/x-php');
        $res1 = $this->actingAs($this->adminUser)->post(route('rrhh.employees.files.store', $employee), [
            'document' => $fakePhpAsPdf,
            'category' => 'otros',
            'title' => 'Exploit PHP',
        ]);
        $res1->assertSessionHasErrors(['document']);

        // 2. Archivo de texto plano renombrado como .jpg (MIME real text/plain)
        $fakeTxtAsJpg = UploadedFile::fake()->create('foto_falsa.jpg', 10, 'text/plain');
        $res2 = $this->actingAs($this->adminUser)->post(route('rrhh.employees.files.store', $employee), [
            'document' => $fakeTxtAsJpg,
            'category' => 'otros',
            'title' => 'Foto Falsa',
        ]);
        $res2->assertSessionHasErrors(['document']);

        // 3. PDF válido con cabecera y MIME application/pdf
        $validPdf = UploadedFile::fake()->createWithContent('valido.pdf', "%PDF-1.4\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF");
        $res3 = $this->actingAs($this->adminUser)->post(route('rrhh.employees.files.store', $employee), [
            'document' => $validPdf,
            'category' => 'otros',
            'title' => 'PDF Legítimo',
        ]);
        $res3->assertRedirect();
        $this->assertDatabaseHas('hr_employee_files', ['title' => 'PDF Legítimo']);

        // 4. Imagen válida real
        $validImage = UploadedFile::fake()->image('foto_perfil.png', 200, 200);
        $res4 = $this->actingAs($this->adminUser)->post(route('rrhh.employees.files.store', $employee), [
            'document' => $validImage,
            'category' => 'otros',
            'title' => 'Foto Legítima',
        ]);
        $res4->assertRedirect();
        $this->assertDatabaseHas('hr_employee_files', ['title' => 'Foto Legítima']);
    }

    /** @test */
    public function test_29_preview_and_download_restrictions_and_security_headers()
    {
        $employee = Employee::create([
            'first_name' => 'Gonzalo',
            'last_name' => 'Montiel',
            'dni' => '64000001',
            'file_number' => 'LEG-PREV-01',
            'hire_date' => '2024-01-01',
            'status' => 'activo',
        ]);

        // PDF: previsualización inline permitida con nosniff
        $pdfFile = UploadedFile::fake()->createWithContent('constancia.pdf', "%PDF-1.4\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF");
        $this->actingAs($this->adminUser)->post(route('rrhh.employees.files.store', $employee), [
            'document' => $pdfFile,
            'category' => 'declaraciones_juradas',
            'title' => 'Alta Temprana AFIP',
        ]);
        $dbPdf = EmployeeFile::where('title', 'Alta Temprana AFIP')->first();

        $respPdfPrev = $this->actingAs($this->adminUser)->get(route('rrhh.employees.files.preview', [$employee, $dbPdf]));
        $respPdfPrev->assertStatus(200);
        $respPdfPrev->assertHeader('Content-Type', 'application/pdf');
        $respPdfPrev->assertHeader('X-Content-Type-Options', 'nosniff');

        // Imagen PNG: previsualización inline permitida
        $pngFile = UploadedFile::fake()->image('dni_dorso.png');
        $this->actingAs($this->adminUser)->post(route('rrhh.employees.files.store', $employee), [
            'document' => $pngFile,
            'category' => 'dni_documentos',
            'title' => 'DNI Dorso',
        ]);
        $dbPng = EmployeeFile::where('title', 'DNI Dorso')->first();

        $respPngPrev = $this->actingAs($this->adminUser)->get(route('rrhh.employees.files.preview', [$employee, $dbPng]));
        $respPngPrev->assertStatus(200);
        $respPngPrev->assertHeader('Content-Type', 'image/png');
        $respPngPrev->assertHeader('X-Content-Type-Options', 'nosniff');

        // Documento DOCX: previsualización no inline, forzado como descarga
        $docxFile = UploadedFile::fake()->createWithContent('manual.docx', "%PDF-1.4\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF");
        $this->actingAs($this->adminUser)->post(route('rrhh.employees.files.store', $employee), [
            'document' => $docxFile,
            'category' => 'capacitaciones',
            'title' => 'Manual de Procedimientos',
        ]);
        $dbDocx = EmployeeFile::where('title', 'Manual de Procedimientos')->first();

        $respDocxPrev = $this->actingAs($this->adminUser)->get(route('rrhh.employees.files.preview', [$employee, $dbDocx]));
        $respDocxPrev->assertStatus(200);
        $respDocxPrev->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString('attachment', $respDocxPrev->headers->get('content-disposition'));
    }

    /** @test */
    public function test_30_logical_voiding_requires_mandatory_reason_and_prevents_double_voiding()
    {
        $employee = Employee::create([
            'first_name' => 'Leandro',
            'last_name' => 'Paredes',
            'dni' => '65000001',
            'file_number' => 'LEG-VOID-01',
            'hire_date' => '2024-01-01',
            'status' => 'activo',
        ]);

        $file = UploadedFile::fake()->createWithContent('recibo_erroneo.pdf', "%PDF-1.4\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF");
        $this->actingAs($this->adminUser)->post(route('rrhh.employees.files.store', $employee), [
            'document' => $file,
            'category' => 'recibos_sueldo',
            'title' => 'Recibo Sueldo Liquidación Errónea',
        ]);
        $dbFile = EmployeeFile::where('title', 'Recibo Sueldo Liquidación Errónea')->first();
        $physicalPath = $dbFile->file_path;

        // 1. Intento de anulación sin motivo obligatorio -> error de validación
        $respVoidFail = $this->actingAs($this->adminUser)->post(route('rrhh.employees.files.void', [$employee, $dbFile]), [
            'reason' => '',
        ]);
        $respVoidFail->assertSessionHasErrors(['reason']);

        // 2. Anulación con motivo válido
        $respVoidSuccess = $this->actingAs($this->adminUser)->post(route('rrhh.employees.files.void', [$employee, $dbFile]), [
            'reason' => 'Liquidación recalculada por error en horas extras',
        ]);
        $respVoidSuccess->assertRedirect();
        $respVoidSuccess->assertSessionHas('ok');

        // Confirmar estado en base de datos
        $freshFile = $dbFile->fresh();
        $this->assertFalse((bool) $freshFile->is_active);
        $this->assertStringContainsString('Liquidación recalculada por error en horas extras', $freshFile->notes);

        // Confirmar auditoría
        $this->assertDatabaseHas('hr_audit_logs', [
            'action' => 'document_void',
            'entity_type' => 'EmployeeFile',
            'entity_id' => $freshFile->id,
            'user_id' => $this->adminUser->id,
        ]);

        // Confirmar que el archivo físico NO se eliminó del disco
        Storage::disk('local')->assertExists($physicalPath);

        // 3. El archivo anulado ya no se puede descargar ni previsualizar normalmente -> 404
        $respDownloadVoided = $this->actingAs($this->adminUser)->get(route('rrhh.employees.files.download', [$employee, $freshFile]));
        $respDownloadVoided->assertStatus(404);

        $respPreviewVoided = $this->actingAs($this->adminUser)->get(route('rrhh.employees.files.preview', [$employee, $freshFile]));
        $respPreviewVoided->assertStatus(404);

        // 4. Intento de anular nuevamente por segunda vez -> controlado
        $respVoidAgain = $this->actingAs($this->adminUser)->post(route('rrhh.employees.files.void', [$employee, $freshFile]), [
            'reason' => 'Intento repetido',
        ]);
        $respVoidAgain->assertRedirect();
        $respVoidAgain->assertSessionHas('error');
    }

    /** @test */
    public function test_31_inactive_catalogs_cannot_be_assigned_to_new_employees_but_preserved_on_existing()
    {
        $inactiveDept = Department::create(['name' => 'Área Desactivada', 'code' => 'INACT-D', 'is_active' => false]);
        $inactivePos = Position::create(['name' => 'Puesto Desactivado', 'code' => 'INACT-P', 'is_active' => false]);
        $inactiveBranch = Branch::create(['name' => 'Sucursal Desactivada', 'code' => 'INACT-B', 'is_active' => false]);
        $inactiveAg = Agreement::create(['name' => 'Convenio Desactivado', 'code' => 'INACT-A', 'is_active' => false]);

        // Intento de alta con catálogos inactivos -> falla de validación
        $payloadNew = [
            'first_name' => 'Nuevo',
            'last_name' => 'Inactivo',
            'dni' => '66000001',
            'file_number' => 'LEG-INACT-01',
            'hire_date' => '2026-01-01',
            'status' => 'activo',
            'department_id' => $inactiveDept->id,
            'position_id' => $inactivePos->id,
            'branch_id' => $inactiveBranch->id,
            'agreement_id' => $inactiveAg->id,
        ];

        $respNew = $this->actingAs($this->adminUser)->post(route('rrhh.employees.store'), $payloadNew);
        $respNew->assertSessionHasErrors(['department_id', 'position_id', 'branch_id', 'agreement_id']);

        // Crear área activa, crear empleado con ella
        $activeDept = Department::create(['name' => 'Área Activa', 'code' => 'ACT-D', 'is_active' => true]);
        $empExisting = Employee::create([
            'first_name' => 'Empleado',
            'last_name' => 'Historico',
            'dni' => '66000002',
            'file_number' => 'LEG-HIST-01',
            'hire_date' => '2025-01-01',
            'status' => 'activo',
            'department_id' => $activeDept->id,
        ]);

        // Desactivar el área
        $activeDept->update(['is_active' => false]);

        // Edición del empleado manteniendo su área histórica inactiva -> debe permitirse
        $payloadUpdate = [
            'first_name' => 'Empleado',
            'last_name' => 'Historico Modificado',
            'dni' => '66000002',
            'file_number' => 'LEG-HIST-01',
            'hire_date' => '2025-01-01',
            'status' => 'activo',
            'department_id' => $activeDept->id, // Mantiene el área que ahora está inactiva
        ];

        $respUpdate = $this->actingAs($this->adminUser)->put(route('rrhh.employees.update', $empExisting), $payloadUpdate);
        $respUpdate->assertRedirect(route('rrhh.employees.show', $empExisting));
        $this->assertEquals($activeDept->id, $empExisting->fresh()->department_id);
    }

    /** @test */
    public function test_32_regression_testing_on_existing_modules_and_controllers()
    {
        // 1. Dashboard principal
        $resDashboard = $this->actingAs($this->adminUser)->get(route('dashboard'));
        $resDashboard->assertStatus(200);

        // 2. Módulo de Usuarios (UserController)
        $resUsers = $this->actingAs($this->adminUser)->get(route('users.index'));
        $resUsers->assertStatus(200);

        // 3. Módulo de Documentos Ventas y Compras
        $resSales = $this->actingAs($this->adminUser)->get('/documents?scope=sale');
        $resSales->assertStatus(200);
        $resPurchases = $this->actingAs($this->adminUser)->get('/documents?scope=purchase');
        $resPurchases->assertStatus(200);

        // 4. Módulo de Clientes y Proveedores
        $resCustomers = $this->actingAs($this->adminUser)->get(route('customers.index'));
        $resCustomers->assertStatus(200);
        $resSuppliers = $this->actingAs($this->adminUser)->get(route('suppliers.index'));
        $resSuppliers->assertStatus(200);

        // 5. Módulos de Tráfico, Rutas y Transportes
        $resTraffic = $this->actingAs($this->adminUser)->get(route('traffic.dashboard'));
        $resTraffic->assertStatus(200);
        $resRoutes = $this->actingAs($this->adminUser)->get(route('traffic.routes.index'));
        $resRoutes->assertStatus(200);
        $resOrders = $this->actingAs($this->adminUser)->get(route('traffic.orders.index'));
        $resOrders->assertStatus(200);
        $resTransportistas = $this->actingAs($this->adminUser)->get(route('traffic.transportistas.index'));
        $resTransportistas->assertStatus(200);
        $resTransportes = $this->actingAs($this->adminUser)->get(route('traffic.transportes.index'));
        $resTransportes->assertStatus(200);

        // 6. Configuración General de Parámetros y Términos
        $resConfig = $this->actingAs($this->adminUser)->get(route('config.parameters.index'));
        $resConfig->assertStatus(200);
        $resTerms = $this->actingAs($this->adminUser)->get(route('terms.edit'));
        $resTerms->assertStatus(200);

        // 7. Contabilidad (Ledger)
        $resLedger = $this->actingAs($this->adminUser)->get(route('ledger.index'));
        $resLedger->assertStatus(200);

        // 8. RR. HH. Dashboard
        $resHr = $this->actingAs($this->adminUser)->get(route('rrhh.dashboard'));
        $resHr->assertStatus(200);
    }
}

