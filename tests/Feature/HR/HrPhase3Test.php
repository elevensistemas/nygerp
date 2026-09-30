<?php

namespace Tests\Feature\HR;

use App\Models\HR\Agreement;
use App\Models\HR\Branch;
use App\Models\HR\Department;
use App\Models\HR\Employee;
use App\Models\HR\Holiday;
use App\Models\HR\LeaveApprovalLog;
use App\Models\HR\LeaveBalance;
use App\Models\HR\LeaveBalanceAdjustment;
use App\Models\HR\LeavePolicy;
use App\Models\HR\LeaveRequest;
use App\Models\HR\LeaveRequestAllocation;
use App\Models\HR\LeaveType;
use App\Models\HR\Position;
use App\Models\User;
use App\Services\HR\HrLeaveCalculationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class HrPhase3Test extends TestCase
{
    use DatabaseTransactions;

    protected $adminUser;
    protected $managerUser;
    protected $employeeUser;
    protected $otherEmployeeUser;
    protected $unrelatedUser;

    protected $managerEmployee;
    protected $activeEmployee;
    protected $otherEmployee;
    protected $inactiveEmployee;

    protected $vacationType;
    protected $medicalType;
    protected $personalDayType;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Storage::fake('public');

        // Limpiar tablas de Fase 3 y Core en entorno de testing
        DB::table('hr_leave_request_allocations')->delete();
        DB::table('hr_leave_approval_logs')->delete();
        DB::table('hr_leave_balance_adjustments')->delete();
        DB::table('hr_leave_requests')->delete();
        DB::table('hr_leave_balances')->delete();
        DB::table('hr_leave_policies')->delete();
        DB::table('hr_holidays')->delete();
        DB::table('hr_leave_types')->delete();
        DB::table('hr_employees')->delete();
        DB::table('hr_departments')->delete();
        DB::table('hr_positions')->delete();

        // 1. Usuarios
        $this->adminUser = User::firstOrCreate(['email' => 'admin_phase3@test.com'], [
            'name' => 'Admin Phase3',
            'password' => Hash::make('password'),
            'role' => User::ROLE_ADMIN,
            'accepted_at' => now(),
        ]);

        $this->managerUser = User::firstOrCreate(['email' => 'manager_phase3@test.com'], [
            'name' => 'Manager Phase3',
            'password' => Hash::make('password'),
            'role' => User::ROLE_USER,
            'accepted_at' => now(),
        ]);

        $this->employeeUser = User::firstOrCreate(['email' => 'emp_phase3@test.com'], [
            'name' => 'Employee Phase3',
            'password' => Hash::make('password'),
            'role' => User::ROLE_USER,
            'accepted_at' => now(),
        ]);

        $this->otherEmployeeUser = User::firstOrCreate(['email' => 'other_emp_phase3@test.com'], [
            'name' => 'Other Employee Phase3',
            'password' => Hash::make('password'),
            'role' => User::ROLE_USER,
            'accepted_at' => now(),
        ]);

        $this->unrelatedUser = User::firstOrCreate(['email' => 'unrelated_phase3@test.com'], [
            'name' => 'Unrelated User',
            'password' => Hash::make('password'),
            'role' => User::ROLE_USER,
            'accepted_at' => now(),
        ]);

        // 2. Área y Puesto
        $dept = Department::create([
            'name' => 'Operaciones Phase3',
            'code' => 'OP-P3',
            'is_active' => true,
        ]);

        $pos = Position::create([
            'name' => 'Analista Phase3',
            'code' => 'AN-P3',
            'is_active' => true,
        ]);

        // 3. Perfiles de Empleado
        $this->managerEmployee = Employee::create([
            'user_id' => $this->managerUser->id,
            'file_number' => 'MGR-001',
            'first_name' => 'Carlos',
            'last_name' => 'Manager',
            'dni' => '30000001',
            'cuil' => '20-30000001-9',
            'personal_email' => 'manager_phase3@test.com',
            'hire_date' => '2018-01-01',
            'status' => 'activo',
            'department_id' => $dept->id,
            'position_id' => $pos->id,
        ]);

        $this->activeEmployee = Employee::create([
            'user_id' => $this->employeeUser->id,
            'file_number' => 'EMP-001',
            'first_name' => 'Juan',
            'last_name' => 'Perez',
            'dni' => '30000002',
            'cuil' => '20-30000002-9',
            'personal_email' => 'emp_phase3@test.com',
            'hire_date' => '2020-01-01',
            'status' => 'activo',
            'department_id' => $dept->id,
            'position_id' => $pos->id,
            'manager_id' => $this->managerEmployee->id,
        ]);

        $this->otherEmployee = Employee::create([
            'user_id' => $this->otherEmployeeUser->id,
            'file_number' => 'EMP-002',
            'first_name' => 'Maria',
            'last_name' => 'Gomez',
            'dni' => '30000003',
            'cuil' => '27-30000003-4',
            'personal_email' => 'other_emp_phase3@test.com',
            'hire_date' => '2021-01-01',
            'status' => 'activo',
            'department_id' => $dept->id,
            'position_id' => $pos->id,
            'manager_id' => null, // Sin manager directo (RRHH directo)
        ]);

        // Inactive employee
        $inactiveUser = User::firstOrCreate(['email' => 'inactive_phase3@test.com'], [
            'name' => 'Inactive User',
            'password' => Hash::make('password'),
            'role' => User::ROLE_USER,
            'accepted_at' => now(),
        ]);

        $this->inactiveEmployee = Employee::create([
            'user_id' => $inactiveUser->id,
            'file_number' => 'EMP-999',
            'first_name' => 'Ex',
            'last_name' => 'Empleado',
            'dni' => '30000999',
            'cuil' => '20-30000999-9',
            'personal_email' => 'inactive_phase3@test.com',
            'hire_date' => '2015-01-01',
            'termination_date' => '2023-01-01',
            'status' => 'egresado',
            'department_id' => $dept->id,
            'position_id' => $pos->id,
        ]);

        // 4. Tipos de Ausencia Base
        $this->vacationType = LeaveType::create([
            'name' => 'Vacaciones Ordinarias',
            'code' => 'VAC',
            'color' => '#0d6efd',
            'deducts_from_balance' => true,
            'days_allowed_per_year' => 14,
            'requires_approval' => true,
            'requires_attachment' => false,
            'allows_half_day' => false,
            'counts_as_working_days' => false, // Corridos
            'min_anticipation_days' => 5,
            'allows_negative_balance' => false,
            'requires_reason' => false,
            'is_active' => true,
        ]);

        $this->medicalType = LeaveType::create([
            'name' => 'Licencia Médica',
            'code' => 'MED',
            'color' => '#dc3545',
            'deducts_from_balance' => false,
            'requires_approval' => true,
            'requires_attachment' => true,
            'allows_half_day' => false,
            'counts_as_working_days' => true, // Hábiles
            'min_anticipation_days' => 0,
            'allows_negative_balance' => true,
            'requires_reason' => true,
            'is_active' => true,
        ]);

        $this->personalDayType = LeaveType::create([
            'name' => 'Día Personal',
            'code' => 'DIA_PERS',
            'color' => '#198754',
            'deducts_from_balance' => true,
            'days_allowed_per_year' => 3,
            'requires_approval' => true,
            'requires_attachment' => false,
            'allows_half_day' => true,
            'counts_as_working_days' => true,
            'min_anticipation_days' => 1,
            'allows_negative_balance' => false,
            'requires_reason' => true,
            'is_active' => true,
        ]);

        // 5. Política para Vacaciones
        $policy = LeavePolicy::create([
            'leave_type_id' => $this->vacationType->id,
            'seniority_years_from' => 0,
            'seniority_years_to' => 15,
            'days_granted' => 14,
            'counts_as_working_days' => false,
            'is_active' => true,
        ]);

        // 6. Saldos Iniciales
        LeaveBalance::create([
            'employee_id' => $this->activeEmployee->id,
            'leave_type_id' => $this->vacationType->id,
            'leave_policy_id' => $policy->id,
            'policy_snapshot' => [
                'policy_id' => $policy->id,
                'days_granted' => 14,
            ],
            'period_year' => 2026,
            'assigned_days' => 14,
            'transferred_days' => 0,
            'adjustment_days' => 0,
            'used_days' => 0,
            'pending_days' => 0,
            'available_days' => 14,
        ]);

        LeaveBalance::create([
            'employee_id' => $this->activeEmployee->id,
            'leave_type_id' => $this->personalDayType->id,
            'period_year' => 2026,
            'assigned_days' => 3,
            'transferred_days' => 0,
            'adjustment_days' => 0,
            'used_days' => 0,
            'pending_days' => 0,
            'available_days' => 3,
        ]);
    }

    /** @test */
    public function test_00_database_isolation_nygerp_testing()
    {
        $db = DB::connection()->getDatabaseName();
        $this->assertEquals('nygerp_testing', $db, 'Must execute strictly on nygerp_testing');
    }

    /** @test */
    public function test_01_leave_calculation_service_calendar_vs_working_days()
    {
        $calcService = app(HrLeaveCalculationService::class);

        $start = Carbon::parse('2026-10-05');
        $end = Carbon::parse('2026-10-09');

        $workingRes = $calcService->calculateDays($start, $end, true, false);
        $this->assertEquals(5, $workingRes['calendar_days']);
        $this->assertEquals(5, $workingRes['working_days']);
        $this->assertEquals(5, $workingRes['computable_days']);

        $endSunday = Carbon::parse('2026-10-11');
        $corridosRes = $calcService->calculateDays($start, $endSunday, false, false);
        $this->assertEquals(7, $corridosRes['calendar_days']);
        $this->assertEquals(7, $corridosRes['computable_days']);

        $habilesRes = $calcService->calculateDays($start, $endSunday, true, false);
        $this->assertEquals(7, $habilesRes['calendar_days']);
        $this->assertEquals(2, $habilesRes['weekend_days']);
        $this->assertEquals(5, $habilesRes['computable_days']);
    }

    /** @test */
    public function test_02_leave_calculation_service_excludes_internal_holidays_and_weekends()
    {
        $calcService = app(HrLeaveCalculationService::class);

        Holiday::create([
            'name' => 'Feriado de Prueba',
            'holiday_date' => '2026-10-07',
            'type' => 'national',
            'is_recurring' => false,
            'is_active' => true,
        ]);

        $start = Carbon::parse('2026-10-05');
        $end = Carbon::parse('2026-10-11');

        $result = $calcService->calculateDays($start, $end, true, false);
        $this->assertEquals(7, $result['calendar_days']);
        $this->assertEquals(2, $result['weekend_days']);
        $this->assertEquals(1, $result['holiday_days']);
        $this->assertEquals(4, $result['computable_days']);
    }

    /** @test */
    public function test_03_leave_calculation_service_half_day()
    {
        $calcService = app(HrLeaveCalculationService::class);

        $start = Carbon::parse('2026-10-05');
        $end = Carbon::parse('2026-10-05');

        $result = $calcService->calculateDays($start, $end, true, true, 'manana');
        $this->assertEquals(0.5, $result['computable_days']);
        $this->assertEquals('manana', $result['half_day_type']);
    }

    /** @test */
    public function test_04_leave_calculation_service_overlap_detection_with_half_day_matrix()
    {
        $calcService = app(HrLeaveCalculationService::class);

        // Solicitud existente: 2026-11-10 al 2026-11-20
        $existing = LeaveRequest::create([
            'employee_id' => $this->activeEmployee->id,
            'leave_type_id' => $this->vacationType->id,
            'start_date' => '2026-11-10',
            'end_date' => '2026-11-20',
            'days_requested' => 11,
            'status' => 'aprobada',
        ]);

        // Variante A: Fechas idénticas
        $overlapA = $calcService->findOverlap($this->activeEmployee->id, Carbon::parse('2026-11-10'), Carbon::parse('2026-11-20'));
        $this->assertNotNull($overlapA);

        // Variante B: Inicio dentro del rango existente
        $overlapB = $calcService->findOverlap($this->activeEmployee->id, Carbon::parse('2026-11-15'), Carbon::parse('2026-11-25'));
        $this->assertNotNull($overlapB);

        // Variante C: Fin dentro del rango existente
        $overlapC = $calcService->findOverlap($this->activeEmployee->id, Carbon::parse('2026-11-05'), Carbon::parse('2026-11-15'));
        $this->assertNotNull($overlapC);

        // Variante D: Contiene completamente el rango existente
        $overlapD = $calcService->findOverlap($this->activeEmployee->id, Carbon::parse('2026-11-01'), Carbon::parse('2026-11-30'));
        $this->assertNotNull($overlapD);

        // Variante E: Sin superposición
        $overlapE = $calcService->findOverlap($this->activeEmployee->id, Carbon::parse('2026-12-01'), Carbon::parse('2026-12-05'));
        $this->assertNull($overlapE);

        // Variante F: Matriz de Medio Día (Mismo día: Mañana vs Tarde no colisiona, Mañana vs Mañana sí)
        $halfMorning = LeaveRequest::create([
            'employee_id' => $this->activeEmployee->id,
            'leave_type_id' => $this->personalDayType->id,
            'start_date' => '2026-12-15',
            'end_date' => '2026-12-15',
            'days_requested' => 0.5,
            'is_half_day' => true,
            'half_day_type' => 'manana',
            'status' => 'aprobada',
        ]);

        // Tarde en el mismo día -> No colisiona
        $overlapTarde = $calcService->findOverlap($this->activeEmployee->id, '2026-12-15', '2026-12-15', true, 'tarde');
        $this->assertNull($overlapTarde, 'Morning and Afternoon half days on same date must coexist');

        // Mañana en el mismo día -> Colisiona
        $overlapManana = $calcService->findOverlap($this->activeEmployee->id, '2026-12-15', '2026-12-15', true, 'manana');
        $this->assertNotNull($overlapManana);

        // Día completo en el mismo día -> Colisiona
        $overlapFull = $calcService->findOverlap($this->activeEmployee->id, '2026-12-15', '2026-12-15', false, null);
        $this->assertNotNull($overlapFull);
    }

    /** @test */
    public function test_05_active_employee_creates_valid_request_with_allocations()
    {
        $response = $this->actingAs($this->employeeUser)->post('/rrhh/portal/requests', [
            'leave_type_id' => $this->vacationType->id,
            'start_date' => Carbon::today()->addDays(10)->format('Y-m-d'),
            'end_date' => Carbon::today()->addDays(16)->format('Y-m-d'),
            'is_half_day' => 0,
            'reason' => 'Vacaciones familiares anuales',
        ]);

        $response->assertRedirect('/rrhh/portal/requests');
        $this->assertDatabaseHas('hr_leave_requests', [
            'employee_id' => $this->activeEmployee->id,
            'leave_type_id' => $this->vacationType->id,
            'status' => 'pendiente_manager',
            'days_count' => 7,
        ]);

        $createdReq = LeaveRequest::where('employee_id', $this->activeEmployee->id)->latest()->first();

        // Verificar tabla hr_leave_request_allocations
        $this->assertDatabaseHas('hr_leave_request_allocations', [
            'leave_request_id' => $createdReq->id,
            'period_year' => Carbon::today()->addDays(10)->year,
            'days_allocated' => 7,
            'status' => 'pending',
        ]);

        // Verificar saldo consolidado
        $balance = LeaveBalance::where('employee_id', $this->activeEmployee->id)
            ->where('leave_type_id', $this->vacationType->id)
            ->where('period_year', Carbon::today()->addDays(10)->year)
            ->first();

        $this->assertEquals(7, $balance->pending_days);
        $this->assertEquals(7, $balance->available_days);
    }

    /** @test */
    public function test_06_user_without_employee_profile_cannot_request()
    {
        $response = $this->actingAs($this->unrelatedUser)->get('/rrhh/portal');
        $response->assertStatus(403);

        $postResponse = $this->actingAs($this->unrelatedUser)->post('/rrhh/portal/requests', [
            'leave_type_id' => $this->vacationType->id,
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-05',
        ]);
        $postResponse->assertStatus(403);
    }

    /** @test */
    public function test_07_inactive_or_terminated_employee_cannot_request()
    {
        $inactiveUser = User::where('email', 'inactive_phase3@test.com')->first();

        $response = $this->actingAs($inactiveUser)->get('/rrhh/portal');
        $response->assertStatus(403);

        $postResponse = $this->actingAs($inactiveUser)->post('/rrhh/portal/requests', [
            'leave_type_id' => $this->vacationType->id,
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-05',
        ]);
        $postResponse->assertStatus(403);
    }

    /** @test */
    public function test_08_inactive_leave_type_cannot_be_requested()
    {
        $this->vacationType->update(['is_active' => false]);

        $response = $this->actingAs($this->employeeUser)->post('/rrhh/portal/requests', [
            'leave_type_id' => $this->vacationType->id,
            'start_date' => Carbon::today()->addDays(10)->format('Y-m-d'),
            'end_date' => Carbon::today()->addDays(15)->format('Y-m-d'),
        ]);

        $response->assertSessionHasErrors('leave_type_id');
    }

    /** @test */
    public function test_09_invalid_dates_end_before_start_rejected()
    {
        $response = $this->actingAs($this->employeeUser)->post('/rrhh/portal/requests', [
            'leave_type_id' => $this->vacationType->id,
            'start_date' => '2026-10-15',
            'end_date' => '2026-10-10',
        ]);

        $response->assertSessionHasErrors('end_date');
    }

    /** @test */
    public function test_10_minimum_anticipation_days_enforced()
    {
        $response = $this->actingAs($this->employeeUser)->post('/rrhh/portal/requests', [
            'leave_type_id' => $this->vacationType->id,
            'start_date' => Carbon::today()->addDays(2)->format('Y-m-d'),
            'end_date' => Carbon::today()->addDays(5)->format('Y-m-d'),
        ]);

        $response->assertSessionHasErrors('start_date');
    }

    /** @test */
    public function test_11_insufficient_balance_rejected_when_negative_disallowed()
    {
        $response = $this->actingAs($this->employeeUser)->post('/rrhh/portal/requests', [
            'leave_type_id' => $this->vacationType->id,
            'start_date' => Carbon::today()->addDays(10)->format('Y-m-d'),
            'end_date' => Carbon::today()->addDays(30)->format('Y-m-d'),
            'reason' => 'Vacaciones muy largas',
        ]);

        $response->assertSessionHasErrors('balance');
    }

    /** @test */
    public function test_12_negative_balance_allowed_when_configured()
    {
        $file = UploadedFile::fake()->create('certificado.pdf', 100, 'application/pdf');

        $response = $this->actingAs($this->employeeUser)->post('/rrhh/portal/requests', [
            'leave_type_id' => $this->medicalType->id,
            'start_date' => Carbon::today()->format('Y-m-d'),
            'end_date' => Carbon::today()->addDays(2)->format('Y-m-d'),
            'reason' => 'Gripe y reposo indicado',
            'attachment' => $file,
        ]);

        $response->assertRedirect('/rrhh/portal/requests');
        $this->assertDatabaseHas('hr_leave_requests', [
            'employee_id' => $this->activeEmployee->id,
            'leave_type_id' => $this->medicalType->id,
            'status' => 'pendiente_manager',
        ]);
    }

    /** @test */
    public function test_13_mandatory_attachment_enforced_and_mime_validated()
    {
        $responseNoFile = $this->actingAs($this->employeeUser)->post('/rrhh/portal/requests', [
            'leave_type_id' => $this->medicalType->id,
            'start_date' => Carbon::today()->format('Y-m-d'),
            'end_date' => Carbon::today()->addDays(2)->format('Y-m-d'),
            'reason' => 'Sin adjunto',
        ]);
        $responseNoFile->assertSessionHasErrors('attachment');

        $invalidFile = UploadedFile::fake()->create('malware.exe', 100, 'application/octet-stream');
        $responseInvalid = $this->actingAs($this->employeeUser)->post('/rrhh/portal/requests', [
            'leave_type_id' => $this->medicalType->id,
            'start_date' => Carbon::today()->format('Y-m-d'),
            'end_date' => Carbon::today()->addDays(2)->format('Y-m-d'),
            'reason' => 'Archivo inválido',
            'attachment' => $invalidFile,
        ]);
        $responseInvalid->assertSessionHasErrors('attachment');
    }

    /** @test */
    public function test_14_mandatory_reason_enforced_when_configured()
    {
        $response = $this->actingAs($this->employeeUser)->post('/rrhh/portal/requests', [
            'leave_type_id' => $this->personalDayType->id,
            'start_date' => Carbon::today()->addDays(2)->format('Y-m-d'),
            'end_date' => Carbon::today()->addDays(2)->format('Y-m-d'),
            'reason' => '',
        ]);

        $response->assertSessionHasErrors('reason');
    }

    /** @test */
    public function test_15_overlapping_request_rejected()
    {
        LeaveRequest::create([
            'employee_id' => $this->activeEmployee->id,
            'leave_type_id' => $this->vacationType->id,
            'start_date' => '2026-11-10',
            'end_date' => '2026-11-15',
            'days_requested' => 6,
            'status' => 'aprobada',
        ]);

        $response = $this->actingAs($this->employeeUser)->post('/rrhh/portal/requests', [
            'leave_type_id' => $this->personalDayType->id,
            'start_date' => '2026-11-12',
            'end_date' => '2026-11-13',
            'reason' => 'Trámite personal',
        ]);

        $response->assertSessionHasErrors('dates');
    }

    /** @test */
    public function test_16_manager_can_view_and_approve_direct_report_request()
    {
        $calcService = app(HrLeaveCalculationService::class);

        $request = LeaveRequest::create([
            'employee_id' => $this->activeEmployee->id,
            'leave_type_id' => $this->personalDayType->id,
            'start_date' => '2026-10-20',
            'end_date' => '2026-10-20',
            'days_requested' => 1,
            'status' => 'pendiente_manager',
            'reason' => 'Trámite bancario',
        ]);

        $calcService->reserveAllocations($request, $this->activeEmployee, $this->personalDayType, [
            2026 => ['year' => 2026, 'days' => 1]
        ]);

        $response = $this->actingAs($this->managerUser)->get('/rrhh/manager/requests');
        $response->assertStatus(200);
        $response->assertSee('Perez');
        $response->assertSee('Trámite bancario');

        // Manager aprueba solicitud -> pasa a 'pendiente_rrhh'
        $approveResponse = $this->actingAs($this->managerUser)->post("/rrhh/manager/requests/{$request->id}/approve");
        $approveResponse->assertRedirect();

        $request->refresh();
        $this->assertEquals('pendiente_rrhh', $request->status);

        // Verificar log
        $this->assertDatabaseHas('hr_leave_approval_logs', [
            'leave_request_id' => $request->id,
            'approver_id' => $this->managerUser->id,
            'action' => 'aprobada_manager',
            'level' => 'manager',
        ]);
    }

    /** @test */
    public function test_17_manager_cannot_view_or_approve_non_direct_report()
    {
        $otherRequest = LeaveRequest::create([
            'employee_id' => $this->otherEmployee->id,
            'leave_type_id' => $this->vacationType->id,
            'start_date' => '2026-10-20',
            'end_date' => '2026-10-25',
            'days_requested' => 6,
            'status' => 'pendiente_rrhh',
        ]);

        $approveResponse = $this->actingAs($this->managerUser)->post("/rrhh/manager/requests/{$otherRequest->id}/approve");
        $approveResponse->assertStatus(403);
    }

    /** @test */
    public function test_18_manager_cannot_approve_own_request()
    {
        $managerRequest = LeaveRequest::create([
            'employee_id' => $this->managerEmployee->id,
            'leave_type_id' => $this->personalDayType->id,
            'start_date' => '2026-10-20',
            'end_date' => '2026-10-20',
            'days_requested' => 1,
            'status' => 'pendiente_rrhh',
        ]);

        $approveResponse = $this->actingAs($this->managerUser)->post("/rrhh/manager/requests/{$managerRequest->id}/approve");
        $approveResponse->assertStatus(403);
    }

    /** @test */
    public function test_19_admin_approves_request_for_employee_without_manager()
    {
        $calcService = app(HrLeaveCalculationService::class);

        $request = LeaveRequest::create([
            'employee_id' => $this->otherEmployee->id,
            'leave_type_id' => $this->vacationType->id,
            'start_date' => '2026-11-02',
            'end_date' => '2026-11-06',
            'days_requested' => 5,
            'status' => 'pendiente_rrhh',
        ]);

        $calcService->reserveAllocations($request, $this->otherEmployee, $this->vacationType, [
            2026 => ['year' => 2026, 'days' => 5]
        ]);

        $response = $this->actingAs($this->adminUser)->post("/rrhh/leave-requests/{$request->id}/approve");
        $response->assertRedirect();

        $request->refresh();
        $this->assertEquals('aprobada', $request->status);
        $this->assertEquals($this->adminUser->id, $request->approved_by);
    }

    /** @test */
    public function test_20_rejection_requires_comment_and_releases_pending_balance()
    {
        $calcService = app(HrLeaveCalculationService::class);

        $request = LeaveRequest::create([
            'employee_id' => $this->activeEmployee->id,
            'leave_type_id' => $this->vacationType->id,
            'start_date' => '2026-11-02',
            'end_date' => '2026-11-06',
            'days_requested' => 5,
            'status' => 'pendiente_rrhh',
        ]);

        $calcService->reserveAllocations($request, $this->activeEmployee, $this->vacationType, [
            2026 => ['year' => 2026, 'days' => 5]
        ]);

        $balance = LeaveBalance::where('employee_id', $this->activeEmployee->id)
            ->where('leave_type_id', $this->vacationType->id)
            ->first();

        $this->assertEquals(5, $balance->pending_days);
        $this->assertEquals(9, $balance->available_days);

        // Rechazo sin comentario -> error
        $responseNoComment = $this->actingAs($this->adminUser)->post("/rrhh/leave-requests/{$request->id}/reject", [
            'comments' => '',
        ]);
        $responseNoComment->assertSessionHasErrors('comments');

        // Rechazo válido
        $response = $this->actingAs($this->adminUser)->post("/rrhh/leave-requests/{$request->id}/reject", [
            'comments' => 'Período de alta demanda operativa no disponible',
        ]);
        $response->assertRedirect();

        $request->refresh();
        $this->assertEquals('rechazada', $request->status);

        // Saldo liberado
        $balance->refresh();
        $this->assertEquals(0, $balance->pending_days);
        $this->assertEquals(14, $balance->available_days);
    }

    /** @test */
    public function test_21_approval_deducts_balance_once_and_double_approval_is_idempotent()
    {
        $calcService = app(HrLeaveCalculationService::class);

        $request = LeaveRequest::create([
            'employee_id' => $this->activeEmployee->id,
            'leave_type_id' => $this->vacationType->id,
            'start_date' => '2026-11-02',
            'end_date' => '2026-11-06',
            'days_requested' => 5,
            'status' => 'pendiente_rrhh',
        ]);

        $calcService->reserveAllocations($request, $this->activeEmployee, $this->vacationType, [
            2026 => ['year' => 2026, 'days' => 5]
        ]);

        // Primera aprobación
        $this->actingAs($this->adminUser)->post("/rrhh/leave-requests/{$request->id}/approve");
        $balance = LeaveBalance::where('employee_id', $this->activeEmployee->id)
            ->where('leave_type_id', $this->vacationType->id)
            ->first();

        $this->assertEquals(5, $balance->used_days);
        $this->assertEquals(0, $balance->pending_days);
        $this->assertEquals(9, $balance->available_days);

        // Segunda aprobación (idempotente)
        $this->actingAs($this->adminUser)->post("/rrhh/leave-requests/{$request->id}/approve");
        $balance->refresh();
        $this->assertEquals(5, $balance->used_days);
        $this->assertEquals(9, $balance->available_days);
    }

    /** @test */
    public function test_22_administrative_cancellation_returns_balance_once()
    {
        $calcService = app(HrLeaveCalculationService::class);

        $request = LeaveRequest::create([
            'employee_id' => $this->activeEmployee->id,
            'leave_type_id' => $this->vacationType->id,
            'start_date' => '2026-11-02',
            'end_date' => '2026-11-06',
            'days_requested' => 5,
            'status' => 'pendiente_rrhh',
        ]);

        $calcService->reserveAllocations($request, $this->activeEmployee, $this->vacationType, [
            2026 => ['year' => 2026, 'days' => 5]
        ]);

        $calcService->approveAllocations($request);
        $request->update(['status' => 'aprobada', 'approved_by' => $this->adminUser->id]);

        $balance = LeaveBalance::where('employee_id', $this->activeEmployee->id)
            ->where('leave_type_id', $this->vacationType->id)
            ->first();

        $this->assertEquals(5, $balance->used_days);
        $this->assertEquals(9, $balance->available_days);

        // Cancelación Administrativa
        $response = $this->actingAs($this->adminUser)->post("/rrhh/leave-requests/{$request->id}/cancel", [
            'comments' => 'Cancelación por reasignación',
        ]);
        $response->assertRedirect();

        $request->refresh();
        $this->assertEquals('cancelada', $request->status);

        $balance->refresh();
        $this->assertEquals(0, $balance->used_days);
        $this->assertEquals(14, $balance->available_days);
    }

    /** @test */
    public function test_23_employee_can_cancel_pending_request_and_release_committed_balance()
    {
        $calcService = app(HrLeaveCalculationService::class);

        $request = LeaveRequest::create([
            'employee_id' => $this->activeEmployee->id,
            'leave_type_id' => $this->personalDayType->id,
            'start_date' => '2026-10-25',
            'end_date' => '2026-10-25',
            'days_requested' => 1,
            'status' => 'pendiente_manager',
        ]);

        $calcService->reserveAllocations($request, $this->activeEmployee, $this->personalDayType, [
            2026 => ['year' => 2026, 'days' => 1]
        ]);

        $balance = LeaveBalance::where('employee_id', $this->activeEmployee->id)
            ->where('leave_type_id', $this->personalDayType->id)
            ->first();

        $this->assertEquals(1, $balance->pending_days);
        $this->assertEquals(2, $balance->available_days);

        $response = $this->actingAs($this->employeeUser)->post("/rrhh/portal/requests/{$request->id}/cancel");
        $response->assertRedirect('/rrhh/portal/requests');

        $request->refresh();
        $this->assertEquals('cancelada', $request->status);

        $balance->refresh();
        $this->assertEquals(0, $balance->pending_days);
        $this->assertEquals(3, $balance->available_days);
    }

    /** @test */
    public function test_24_manual_balance_adjustment_requires_reason_and_logs_audit()
    {
        $balance = LeaveBalance::where('employee_id', $this->activeEmployee->id)
            ->where('leave_type_id', $this->vacationType->id)
            ->first();

        $response = $this->actingAs($this->adminUser)->post("/rrhh/leave-balances/{$balance->id}/adjust", [
            'type' => 'positive',
            'days' => 3,
            'reason' => 'Reconocimiento por antigüedad adicional',
        ]);

        $response->assertRedirect();
        $balance->refresh();

        $this->assertEquals(3, $balance->adjustment_days);
        $this->assertEquals(17, $balance->available_days);

        $this->assertDatabaseHas('hr_leave_balance_adjustments', [
            'employee_id' => $this->activeEmployee->id,
            'leave_type_id' => $this->vacationType->id,
            'previous_balance' => 14,
            'new_balance' => 17,
            'difference' => 3,
            'user_id' => $this->adminUser->id,
        ]);
    }

    /** @test */
    public function test_25_manual_balance_adjustment_prevents_negative_balance_when_disallowed()
    {
        $balance = LeaveBalance::where('employee_id', $this->activeEmployee->id)
            ->where('leave_type_id', $this->vacationType->id)
            ->first();

        $response = $this->actingAs($this->adminUser)->post("/rrhh/leave-balances/{$balance->id}/adjust", [
            'type' => 'negative',
            'days' => 20,
            'reason' => 'Descuento erróneo',
        ]);

        $response->assertSessionHasErrors('days');
        $balance->refresh();
        $this->assertEquals(14, $balance->available_days);
    }

    /** @test */
    public function test_26_calendar_privacy_shared_calendar_does_not_expose_medical_diagnoses()
    {
        LeaveRequest::create([
            'employee_id' => $this->activeEmployee->id,
            'leave_type_id' => $this->medicalType->id,
            'start_date' => '2026-10-15',
            'end_date' => '2026-10-16',
            'days_requested' => 2,
            'status' => 'aprobada',
            'reason' => 'Diagnóstico confidencial médico CIRUGIA',
        ]);

        $response = $this->actingAs($this->otherEmployeeUser)->get('/rrhh/portal/calendar?month=2026-10');
        $response->assertStatus(200);
        $response->assertDontSee('CIRUGIA');
    }

    /** @test */
    public function test_27_medical_attachment_download_is_protected_and_manager_cannot_download()
    {
        Storage::disk('local')->put('hr/leave_attachments/safe_uuid/doc_secret.pdf', 'Contenido confidencial');

        $request = LeaveRequest::create([
            'employee_id' => $this->activeEmployee->id,
            'leave_type_id' => $this->medicalType->id,
            'start_date' => '2026-10-15',
            'end_date' => '2026-10-16',
            'days_requested' => 2,
            'status' => 'aprobada',
            'attachment_path' => 'hr/leave_attachments/safe_uuid/doc_secret.pdf',
            'attachment_original_name' => 'doc_secret.pdf',
        ]);

        // Admin puede descargar
        $adminResponse = $this->actingAs($this->adminUser)->get("/rrhh/leave-requests/{$request->id}/attachment");
        $adminResponse->assertStatus(200);

        // Otro usuario / manager no autorizado recibe 403
        $unauthResponse = $this->actingAs($this->otherEmployeeUser)->get("/rrhh/leave-requests/{$request->id}/attachment");
        $unauthResponse->assertStatus(403);
    }

    /** @test */
    public function test_28_admin_routes_protected_by_hr_access_admin()
    {
        $nonAdmin = $this->employeeUser;

        $this->actingAs($nonAdmin)->get('/rrhh/leave-types')->assertStatus(403);
        $this->actingAs($nonAdmin)->get('/rrhh/leave-policies')->assertStatus(403);
        $this->actingAs($nonAdmin)->get('/rrhh/holidays')->assertStatus(403);
        $this->actingAs($nonAdmin)->get('/rrhh/leave-balances')->assertStatus(403);
        $this->actingAs($nonAdmin)->get('/rrhh/leave-requests')->assertStatus(403);
    }

    /** @test */
    public function test_29_manager_routes_protected_by_hr_access_manager()
    {
        $this->actingAs($this->otherEmployeeUser)->get('/rrhh/manager/requests')->assertStatus(403);
        $this->actingAs($this->otherEmployeeUser)->get('/rrhh/manager/calendar')->assertStatus(403);

        $this->actingAs($this->managerUser)->get('/rrhh/manager/requests')->assertStatus(200);
        $this->actingAs($this->managerUser)->get('/rrhh/manager/calendar')->assertStatus(200);
    }

    /** @test */
    public function test_30_employee_portal_routes_protected_by_hr_access_employee()
    {
        $this->actingAs($this->unrelatedUser)->get('/rrhh/portal')->assertStatus(403);
        $this->actingAs($this->unrelatedUser)->get('/rrhh/portal/requests')->assertStatus(403);
        $this->actingAs($this->unrelatedUser)->get('/rrhh/portal/requests/create')->assertStatus(403);

        $this->actingAs($this->employeeUser)->get('/rrhh/portal')->assertStatus(200);
        $this->actingAs($this->employeeUser)->get('/rrhh/portal/requests')->assertStatus(200);
        $this->actingAs($this->employeeUser)->get('/rrhh/portal/requests/create')->assertStatus(200);
    }

    /** @test */
    public function test_31_csv_export_generates_valid_csv_stream()
    {
        LeaveRequest::create([
            'employee_id' => $this->activeEmployee->id,
            'leave_type_id' => $this->vacationType->id,
            'start_date' => '2026-12-01',
            'end_date' => '2026-12-10',
            'days_requested' => 10,
            'status' => 'aprobada',
            'reason' => 'Vacaciones fin de año',
        ]);

        $response = $this->actingAs($this->adminUser)->get('/rrhh/leave-requests/export-csv');
        $response->assertStatus(200);
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    /** @test */
    public function test_32_holiday_crud_management_and_mobile_vs_fixed_recurrence()
    {
        $storeRes = $this->actingAs($this->adminUser)->post('/rrhh/holidays', [
            'name' => 'Feriado Móvil San Martín',
            'holiday_date' => '2026-08-17',
            'type' => 'national',
            'is_recurring' => 0, // Móvil
            'is_active' => 1,
        ]);
        $storeRes->assertRedirect('/rrhh/holidays');
        $this->assertDatabaseHas('hr_holidays', [
            'name' => 'Feriado Móvil San Martín',
            'date' => '2026-08-17',
            'is_recurring' => 0,
        ]);

        $calcService = app(HrLeaveCalculationService::class);
        // En 2026 es feriado
        $this->assertTrue($calcService->isHoliday(Carbon::parse('2026-08-17')));
        // En 2027 NO debe ser feriado por no ser recurrente
        $this->assertFalse($calcService->isHoliday(Carbon::parse('2027-08-17')));
    }

    /** @test */
    public function test_33_leave_type_and_policy_crud_management_and_overlapping_range_rejection()
    {
        $storeTypeRes = $this->actingAs($this->adminUser)->post('/rrhh/leave-types', [
            'code' => 'STUDY',
            'name' => 'Licencia por Estudio',
            'color' => '#6f42c1',
            'counts_as_working_days' => 1,
            'deducts_from_balance' => 0,
            'requires_approval' => 1,
            'requires_attachment' => 1,
            'requires_reason' => 1,
            'allows_half_day' => 0,
            'min_anticipation_days' => 3,
            'is_active' => 1,
        ]);
        $storeTypeRes->assertRedirect('/rrhh/leave-types');
        $this->assertDatabaseHas('hr_leave_types', ['code' => 'STUDY']);

        $studyType = LeaveType::where('code', 'STUDY')->first();

        // Política 1: 0 a 5 años
        $this->actingAs($this->adminUser)->post('/rrhh/leave-policies', [
            'leave_type_id' => $studyType->id,
            'seniority_years_from' => 0,
            'seniority_years_to' => 5,
            'days_granted' => 10,
            'is_active' => 1,
        ]);

        // Política 2 superpuesta: 3 a 8 años -> Debe ser rechazada
        $overlapRes = $this->actingAs($this->adminUser)->post('/rrhh/leave-policies', [
            'leave_type_id' => $studyType->id,
            'seniority_years_from' => 3,
            'seniority_years_to' => 8,
            'days_granted' => 15,
            'is_active' => 1,
        ]);
        $overlapRes->assertSessionHasErrors('seniority_years_from');
    }

    /** @test */
    public function test_34_policy_snapshot_is_preserved_when_editing_future_policies()
    {
        $calcService = app(HrLeaveCalculationService::class);

        // Crear saldo para 2026 (14 días snapshot)
        $balance2026 = $calcService->getOrCreateBalance($this->activeEmployee, $this->vacationType, 2026);
        $this->assertEquals(14, $balance2026->assigned_days);
        $this->assertNotNull($balance2026->policy_snapshot);

        // Modificar la política actual a 20 días
        $policy = LeavePolicy::where('leave_type_id', $this->vacationType->id)->first();
        $policy->update(['days_granted' => 20]);

        // Saldo existente de 2026 no debe mutar silenciosamente
        $balance2026->refresh();
        $this->assertEquals(14, $balance2026->assigned_days, 'Historical assigned_days snapshot must remain intact');

        // Nuevo saldo para 2027 toma la nueva política
        $balance2027 = $calcService->getOrCreateBalance($this->activeEmployee, $this->vacationType, 2027);
        $this->assertEquals(20, $balance2027->assigned_days);
    }

    /** @test */
    public function test_35_policy_priority_agreement_over_general_and_type_defaults()
    {
        $calcService = app(HrLeaveCalculationService::class);

        $agreement = Agreement::create(['name' => 'Convenio Metalúrgico', 'code' => 'UOM-TEST', 'is_active' => true]);
        $this->activeEmployee->update(['agreement_id' => $agreement->id]);

        // Política específica de convenio: 18 días
        LeavePolicy::create([
            'leave_type_id' => $this->vacationType->id,
            'agreement_id' => $agreement->id,
            'seniority_years_from' => 0,
            'seniority_years_to' => 15,
            'days_granted' => 18,
            'is_active' => true,
        ]);

        $policyChosen = $calcService->determinePolicy($this->activeEmployee, $this->vacationType);
        $this->assertNotNull($policyChosen);
        $this->assertEquals($agreement->id, $policyChosen->agreement_id);
        $this->assertEquals(18, $policyChosen->days_granted);
    }

    /** @test */
    public function test_36_multi_year_request_allocates_days_across_periods_and_atomic_rollback_on_insufficient_second_balance()
    {
        $calcService = app(HrLeaveCalculationService::class);

        // Crear saldos para 2026 (14 días) y 2027 (14 días)
        $balance2026 = $calcService->getOrCreateBalance($this->activeEmployee, $this->vacationType, 2026);
        $balance2027 = $calcService->getOrCreateBalance($this->activeEmployee, $this->vacationType, 2027);

        // Solicitud cruzando año: 28/12/2026 al 04/01/2027 (4 días en 2026, 4 días en 2027 = 8 días corridos)
        $response = $this->actingAs($this->employeeUser)->post('/rrhh/portal/requests', [
            'leave_type_id' => $this->vacationType->id,
            'start_date' => '2026-12-28',
            'end_date' => '2027-01-04',
            'reason' => 'Vacaciones de año nuevo',
        ]);

        $response->assertRedirect('/rrhh/portal/requests');

        $req = LeaveRequest::where('employee_id', $this->activeEmployee->id)->latest()->first();
        $this->assertEquals(8, $req->days_count);

        // Debe haber 2 allocations
        $this->assertEquals(2, $req->allocations()->count());
        $alloc2026 = $req->allocations()->where('period_year', 2026)->first();
        $alloc2027 = $req->allocations()->where('period_year', 2027)->first();

        $this->assertEquals(4, $alloc2026->days_allocated);
        $this->assertEquals(4, $alloc2027->days_allocated);

        $balance2026->refresh();
        $balance2027->refresh();
        $this->assertEquals(4, $balance2026->pending_days);
        $this->assertEquals(4, $balance2027->pending_days);

        // Caso B: Si el saldo de 2027 fuera insuficiente, toda la operación debe revertirse
        $calcService->releaseAllocations($req);
        $req->update(['status' => 'cancelada']);
        $balance2026->refresh();
        $this->assertEquals(0, $balance2026->pending_days);

        $balance2027->update(['assigned_days' => 1, 'pending_days' => 0, 'available_days' => 1]); // Insuficiente para pedir 4 días

        $failResponse = $this->actingAs($this->employeeUser)->post('/rrhh/portal/requests', [
            'leave_type_id' => $this->vacationType->id,
            'start_date' => '2026-12-28',
            'end_date' => '2027-01-04',
            'reason' => 'Falla por segundo período',
        ]);

        $failResponse->assertSessionHasErrors('balance');

        // Verificar rollback atómico: el saldo 2026 no debe haber quedado modificado con pending_days
        $balance2026->refresh();
        $this->assertEquals(0, $balance2026->pending_days);
    }

    /** @test */
    public function test_37_idempotency_token_protects_against_duplicate_submission()
    {
        $token = (string) Str::uuid();

        // Primer envío
        $res1 = $this->actingAs($this->employeeUser)->post('/rrhh/portal/requests', [
            'leave_type_id' => $this->vacationType->id,
            'start_date' => Carbon::today()->addDays(10)->format('Y-m-d'),
            'end_date' => Carbon::today()->addDays(12)->format('Y-m-d'),
            'idempotency_token' => $token,
        ]);
        $res1->assertRedirect();

        // Segundo envío idéntico con el mismo token -> no duplica solicitud ni compromete saldo
        $res2 = $this->actingAs($this->employeeUser)->post('/rrhh/portal/requests', [
            'leave_type_id' => $this->vacationType->id,
            'start_date' => Carbon::today()->addDays(10)->format('Y-m-d'),
            'end_date' => Carbon::today()->addDays(12)->format('Y-m-d'),
            'idempotency_token' => $token,
        ]);
        $res2->assertRedirect();

        $this->assertEquals(1, LeaveRequest::where('idempotency_token', $token)->count());
    }

    /** @test */
    public function test_38_safe_physical_attachment_path_does_not_contain_pii()
    {
        $file = UploadedFile::fake()->create('certificado_medico.pdf', 100, 'application/pdf');

        $response = $this->actingAs($this->employeeUser)->post('/rrhh/portal/requests', [
            'leave_type_id' => $this->medicalType->id,
            'start_date' => Carbon::today()->format('Y-m-d'),
            'end_date' => Carbon::today()->addDays(1)->format('Y-m-d'),
            'reason' => 'Reposo',
            'attachment' => $file,
        ]);

        $response->assertRedirect();

        $req = LeaveRequest::where('employee_id', $this->activeEmployee->id)->latest()->first();
        $this->assertNotNull($req->attachment_path);

        // Verificar que no contenga DNI, file_number ni nombre en la ruta física
        $this->assertStringNotContainsString($this->activeEmployee->dni, $req->attachment_path);
        $this->assertStringNotContainsString($this->activeEmployee->file_number, $req->attachment_path);
        $this->assertStringNotContainsString('Perez', $req->attachment_path);
        $this->assertStringNotContainsString('Juan', $req->attachment_path);
    }

    /** @test */
    public function test_39_ajax_calculate_preview_is_strictly_scoped_to_auth_user()
    {
        $response = $this->actingAs($this->employeeUser)->postJson('/rrhh/portal/requests/calculate-preview', [
            'leave_type_id' => $this->vacationType->id,
            'start_date' => Carbon::today()->addDays(10)->format('Y-m-d'),
            'end_date' => Carbon::today()->addDays(15)->format('Y-m-d'),
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'total_computable_days',
            'periods_breakdown',
            'has_overlap',
        ]);
    }

    /** @test */
    public function test_40_two_tier_workflow_manager_approval_keeps_pending_rrhh_before_final_deduction()
    {
        $calcService = app(HrLeaveCalculationService::class);

        // Crear solicitud con manager asignado
        $request = LeaveRequest::create([
            'employee_id' => $this->activeEmployee->id,
            'leave_type_id' => $this->vacationType->id,
            'start_date' => '2026-11-10',
            'end_date' => '2026-11-14',
            'days_requested' => 5,
            'status' => 'pendiente_manager',
        ]);

        $calcService->reserveAllocations($request, $this->activeEmployee, $this->vacationType, [
            2026 => ['year' => 2026, 'days' => 5]
        ]);

        $balance = LeaveBalance::where('employee_id', $this->activeEmployee->id)
            ->where('leave_type_id', $this->vacationType->id)
            ->first();

        // 1. Manager aprueba -> pasa a 'pendiente_rrhh', pero used_days sigue en 0
        $this->actingAs($this->managerUser)->post("/rrhh/manager/requests/{$request->id}/approve");
        $request->refresh();
        $balance->refresh();

        $this->assertEquals('pendiente_rrhh', $request->status);
        $this->assertEquals(5, $balance->pending_days);
        $this->assertEquals(0, $balance->used_days, 'Manager approval must not deduct as used_days yet');

        // 2. RR. HH. otorga aprobación final -> pasa a 'aprobada', pending_days a 0, used_days a 5
        $this->actingAs($this->adminUser)->post("/rrhh/leave-requests/{$request->id}/approve");
        $request->refresh();
        $balance->refresh();

        $this->assertEquals('aprobada', $request->status);
        $this->assertEquals(0, $balance->pending_days);
        $this->assertEquals(5, $balance->used_days);
        $this->assertEquals(9, $balance->available_days);
    }

    /** @test */
    public function test_41_idempotency_token_unique_constraint_at_database_level()
    {
        $token = (string) Str::uuid();

        LeaveRequest::create([
            'employee_id' => $this->activeEmployee->id,
            'leave_type_id' => $this->vacationType->id,
            'start_date' => '2026-11-01',
            'end_date' => '2026-11-02',
            'days_requested' => 2,
            'status' => 'pendiente_rrhh',
            'idempotency_token' => $token,
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        // Intento directo en BD de insertar mismo token debe violar constraint UNIQUE
        LeaveRequest::create([
            'employee_id' => $this->activeEmployee->id,
            'leave_type_id' => $this->vacationType->id,
            'start_date' => '2026-11-03',
            'end_date' => '2026-11-04',
            'days_requested' => 2,
            'status' => 'pendiente_rrhh',
            'idempotency_token' => $token,
        ]);
    }

    /** @test */
    public function test_42_max_days_limit_enforcement_and_exact_boundary()
    {
        // Configurar tipo de ausencia con límite máximo de 3 días
        $type = LeaveType::create([
            'name' => 'Permiso Especial Corto',
            'code' => 'PEC_TEST',
            'days_allowed_per_year' => 10,
            'max_days_limit' => 3,
            'calculation_unit' => 'corridos',
            'requires_approval' => true,
            'is_active' => true,
        ]);

        // Caso A: Solicitud de 4 días supera límite -> Rechazada
        $failResponse = $this->actingAs($this->employeeUser)->post('/rrhh/portal/requests', [
            'leave_type_id' => $type->id,
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-04', // 4 días
            'reason' => 'Excede máximo permitido',
        ]);
        $failResponse->assertSessionHasErrors('dates');

        // Caso B: Solicitud de exactamente 3 días -> Aceptada
        $okResponse = $this->actingAs($this->employeeUser)->post('/rrhh/portal/requests', [
            'leave_type_id' => $type->id,
            'start_date' => '2026-10-10',
            'end_date' => '2026-10-12', // 3 días exactos
            'reason' => 'Exactamente el máximo permitido',
        ]);
        $okResponse->assertRedirect('/rrhh/portal/requests');
        $this->assertDatabaseHas('hr_leave_requests', [
            'employee_id' => $this->activeEmployee->id,
            'leave_type_id' => $type->id,
            'days_count' => 3,
        ]);
    }

    /** @test */
    public function test_43_closed_period_blocks_requests_approvals_and_adjustments()
    {
        $calcService = app(HrLeaveCalculationService::class);
        $balance = $calcService->getOrCreateBalance($this->activeEmployee, $this->vacationType, 2027);
        $balance->update(['is_closed' => true, 'closure_reason' => 'Cierre contable anual 2027']);

        // 1. Solicitud en período cerrado -> Bloqueada
        $reqResponse = $this->actingAs($this->employeeUser)->post('/rrhh/portal/requests', [
            'leave_type_id' => $this->vacationType->id,
            'start_date' => '2027-06-01',
            'end_date' => '2027-06-05',
            'reason' => 'Solicitud extemporánea en año cerrado',
        ]);
        $reqResponse->assertSessionHasErrors('balance');

        // 2. Ajuste manual en período cerrado -> Bloqueado
        $adjResponse = $this->actingAs($this->adminUser)->post("/rrhh/leave-balances/{$balance->id}/adjust", [
            'days' => 2,
            'type' => 'positive',
            'reason' => 'Ajuste en período cerrado no permitido',
        ]);
        $adjResponse->assertSessionHasErrors('days');

        // 3. Aprobación en período cerrado -> Bloqueada
        $pendingReq = LeaveRequest::create([
            'employee_id' => $this->activeEmployee->id,
            'leave_type_id' => $this->vacationType->id,
            'start_date' => '2027-11-01',
            'end_date' => '2027-11-02',
            'days_requested' => 2,
            'status' => 'pendiente_rrhh',
        ]);
        LeaveRequestAllocation::create([
            'leave_request_id' => $pendingReq->id,
            'leave_balance_id' => $balance->id,
            'period_year' => 2027,
            'days_allocated' => 2,
            'status' => 'pending',
        ]);

        $this->expectException(\RuntimeException::class);
        $calcService->approveAllocations($pendingReq);
    }

    /** @test */
    public function test_44_multiannual_full_workflow_and_cancellation_idempotency()
    {
        $calcService = app(HrLeaveCalculationService::class);
        $bal2026 = $calcService->getOrCreateBalance($this->activeEmployee, $this->vacationType, 2026);
        $bal2027 = $calcService->getOrCreateBalance($this->activeEmployee, $this->vacationType, 2027);

        // Solicitud cruzando año (28/12/2026 al 04/01/2027 = 8 días: 4 en 2026 y 4 en 2027)
        $this->actingAs($this->employeeUser)->post('/rrhh/portal/requests', [
            'leave_type_id' => $this->vacationType->id,
            'start_date' => '2026-12-28',
            'end_date' => '2027-01-04',
            'reason' => 'Vacaciones de fin de año',
        ]);

        $req = LeaveRequest::where('employee_id', $this->activeEmployee->id)->latest()->first();
        $this->assertEquals(2, $req->allocations()->count());

        // Aprobación final por RR. HH.
        $this->actingAs($this->adminUser)->post("/rrhh/leave-requests/{$req->id}/approve");
        $bal2026->refresh();
        $bal2027->refresh();

        $this->assertEquals(4, $bal2026->used_days);
        $this->assertEquals(0, $bal2026->pending_days);
        $this->assertEquals(4, $bal2027->used_days);
        $this->assertEquals(0, $bal2027->pending_days);

        // Cancelación administrativa por RR. HH.
        $this->actingAs($this->adminUser)->post("/rrhh/leave-requests/{$req->id}/cancel", [
            'reason' => 'Cancelación de común acuerdo por necesidades operativas',
        ]);

        $bal2026->refresh();
        $bal2027->refresh();
        $this->assertEquals(0, $bal2026->used_days);
        $this->assertEquals(0, $bal2027->used_days);
        $this->assertEquals(14, $bal2026->available_days);
        $this->assertEquals(14, $bal2027->available_days);

        // Reintento de cancelación no debe duplicar devoluciones
        $calcService->releaseAllocations($req, true);
        $bal2026->refresh();
        $bal2027->refresh();
        $this->assertEquals(14, $bal2026->available_days);
        $this->assertEquals(14, $bal2027->available_days);

        // Allocations se conservan marcadas como 'released' para histórico
        $this->assertEquals(2, $req->allocations()->where('status', 'released')->count());
    }

    /** @test */
    public function test_45_balance_reconciliation_detects_and_repairs_discrepancies_with_audit()
    {
        $calcService = app(HrLeaveCalculationService::class);
        $balance = $calcService->getOrCreateBalance($this->activeEmployee, $this->vacationType, 2026);

        // Caso 1: Saldo consistente -> No produce alteraciones ni logs
        $beforeAuditCount = \App\Models\HR\HrAuditLog::where('action', 'reconciliacion_saldo')->count();
        $calcService->reconcileBalance($balance);
        $this->assertEquals($beforeAuditCount, \App\Models\HR\HrAuditLog::where('action', 'reconciliacion_saldo')->count());

        // Caso 2: Corromper intencionalmente pending_days y used_days en la fila
        $balance->update([
            'pending_days' => 99,
            'used_days' => 50,
            'available_days' => -135,
        ]);

        // Reconciliación
        $reconciled = $calcService->reconcileBalance($balance);

        $this->assertEquals(0, $reconciled->pending_days);
        $this->assertEquals(0, $reconciled->used_days);
        $this->assertEquals(14, $reconciled->available_days);
        $this->assertEquals(14, $reconciled->assigned_days, 'assigned_days must never be mutated by reconciliation');

        // Verificar que quedó registrado en el Registro de Auditoría
        $this->assertDatabaseHas('hr_audit_logs', [
            'action' => 'reconciliacion_saldo',
            'entity_type' => 'hr_leave_balances',
            'entity_id' => $balance->id,
        ]);
    }

    /** @test */
    public function test_46_csv_export_neutralizes_formula_injection_and_protects_medical_privacy()
    {
        // Crear un empleado y solicitud con posibles caracteres de inyección CSV
        $maliciousEmployee = Employee::create([
            'first_name' => '=CMD|',
            'last_name' => '+2+5',
            'dni' => '@DNI_TEST',
            'file_number' => '-LEG123',
            'status' => 'activo',
            'hire_date' => '2024-01-01',
            'department_id' => $this->activeEmployee->department_id,
        ]);

        LeaveRequest::create([
            'employee_id' => $maliciousEmployee->id,
            'leave_type_id' => $this->vacationType->id,
            'start_date' => '2026-11-01',
            'end_date' => '2026-11-02',
            'days_requested' => 2,
            'status' => 'aprobada',
            'reason' => 'Consulta normal',
            'confidential_notes' => 'DIAGNOSTICO_SECRETO_NO_EXPORTAR',
            'attachment_path' => 'hr/leave_attachments/secret_path.pdf',
        ]);

        $response = $this->actingAs($this->adminUser)->get('/rrhh/leave-requests/export-csv');
        $response->assertStatus(200);

        $content = $response->streamedContent();

        // 1. Debe neutralizar caracteres iniciales con prefijo '
        $this->assertStringContainsString("'+2+5", $content);
        $this->assertStringContainsString("'@DNI_TEST", $content);
        $this->assertStringContainsString("'-LEG123", $content);

        // 2. No debe incluir confidential_notes ni attachment_path
        $this->assertStringNotContainsString('DIAGNOSTICO_SECRETO_NO_EXPORTAR', $content);
        $this->assertStringNotContainsString('secret_path.pdf', $content);
    }

    /** @test */
    public function test_47_manager_receives_403_on_confidential_notes_and_medical_attachment()
    {
        $file = UploadedFile::fake()->create('certificado_medico.pdf', 500, 'application/pdf');

        $req = LeaveRequest::create([
            'employee_id' => $this->activeEmployee->id,
            'leave_type_id' => $this->medicalType->id,
            'start_date' => '2026-11-05',
            'end_date' => '2026-11-06',
            'days_requested' => 2,
            'status' => 'pendiente_manager',
            'reason' => 'Reposo por indicación médica',
            'confidential_notes' => 'Hipertensión severa - Tratamiento estricto',
            'attachment_path' => 'hr/leave_attachments/test_hash/cert.pdf',
            'attachment_original_name' => 'certificado_medico.pdf',
        ]);

        Storage::disk('local')->put('hr/leave_attachments/test_hash/cert.pdf', 'fake-pdf-content');

        // 1. Manager intenta descargar archivo médico de su reporte -> 403 Forbidden
        $response = $this->actingAs($this->managerUser)->get("/rrhh/leave-requests/{$req->id}/attachment");
        $response->assertStatus(403);

        // 2. Colaborador dueño sí puede descargar su propio archivo desde el portal
        $empResponse = $this->actingAs($this->employeeUser)->get("/rrhh/portal/requests/{$req->id}/attachment");
        $empResponse->assertStatus(200);

        // 3. Admin sí puede descargar
        $adminResponse = $this->actingAs($this->adminUser)->get("/rrhh/leave-requests/{$req->id}/attachment");
        $adminResponse->assertStatus(200);

        // 4. Otro empleado no autorizado recibe 403 al intentar descargar archivo ajeno desde el portal
        $unauthResponse = $this->actingAs($this->otherEmployeeUser)->get("/rrhh/portal/requests/{$req->id}/attachment");
        $unauthResponse->assertStatus(403);
    }

    /** @test */
    public function test_48_double_submission_and_concurrency_reentrance_guarantees()
    {
        $calcService = app(HrLeaveCalculationService::class);
        $balance = $calcService->getOrCreateBalance($this->activeEmployee, $this->vacationType, 2026);

        $req = LeaveRequest::create([
            'employee_id' => $this->activeEmployee->id,
            'leave_type_id' => $this->vacationType->id,
            'start_date' => '2026-11-15',
            'end_date' => '2026-11-17',
            'days_requested' => 3,
            'status' => 'pendiente_rrhh',
        ]);
        $calcService->reserveAllocations($req, $this->activeEmployee, $this->vacationType, [
            2026 => ['year' => 2026, 'days' => 3]
        ]);

        $balance->refresh();
        $this->assertEquals(3, $balance->pending_days);

        // Doble aprobación concurrente/reentrante
        $this->actingAs($this->adminUser)->post("/rrhh/leave-requests/{$req->id}/approve");
        $this->actingAs($this->adminUser)->post("/rrhh/leave-requests/{$req->id}/approve");

        $balance->refresh();
        $this->assertEquals(0, $balance->pending_days);
        $this->assertEquals(3, $balance->used_days);
        $this->assertEquals(11, $balance->available_days);

        // Doble cancelación concurrente/reentrante
        $this->actingAs($this->adminUser)->post("/rrhh/leave-requests/{$req->id}/cancel", ['reason' => 'Motivo A']);
        $this->actingAs($this->adminUser)->post("/rrhh/leave-requests/{$req->id}/cancel", ['reason' => 'Motivo B']);

        $balance->refresh();
        $this->assertEquals(0, $balance->used_days);
        $this->assertEquals(0, $balance->pending_days);
        $this->assertEquals(14, $balance->available_days);
    }

    /** @test */
    public function test_49_admin_can_view_create_leave_request_page_and_calculate_preview()
    {
        $response = $this->actingAs($this->adminUser)->get('/rrhh/leave-requests/create?employee_id=' . $this->activeEmployee->id);
        $response->assertStatus(200);
        $response->assertSee('Asignar Vacaciones o Licencia');

        // Test calculate preview AJAX
        $ajaxResponse = $this->actingAs($this->adminUser)->postJson('/rrhh/leave-requests/calculate-preview', [
            'employee_id' => $this->activeEmployee->id,
            'leave_type_id' => $this->vacationType->id,
            'date_from' => '2026-10-01',
            'date_to' => '2026-10-07',
        ]);

        $ajaxResponse->assertStatus(200);
        $ajaxResponse->assertJson([
            'success' => true,
            'total_computable_days' => 7,
        ]);
    }

    /** @test */
    public function test_50_admin_can_assign_vacations_with_immediate_approval()
    {
        $calcService = app(HrLeaveCalculationService::class);
        $balance = $calcService->getOrCreateBalance($this->activeEmployee, $this->vacationType, 2026);
        $initialAvailable = (float) $balance->available_days;

        $response = $this->actingAs($this->adminUser)->post('/rrhh/leave-requests', [
            'employee_id' => $this->activeEmployee->id,
            'leave_type_id' => $this->vacationType->id,
            'date_from' => '2026-11-01',
            'date_to' => '2026-11-05',
            'reason' => 'Vacaciones otorgadas por dirección',
            'auto_approve' => '1',
        ]);

        $response->assertRedirect();

        $created = LeaveRequest::where('employee_id', $this->activeEmployee->id)
            ->where('date_from', '2026-11-01')
            ->first();

        $this->assertNotNull($created);
        $this->assertEquals('aprobada', $created->status);
        $this->assertEquals(5, $created->days_count);
        $this->assertEquals($this->adminUser->id, $created->approved_by);

        // Verificar que el saldo fue descontado inmediatamente
        $balance->refresh();
        $this->assertEquals(5, $balance->used_days);
        $this->assertEquals(0, $balance->pending_days);
        $this->assertEquals($initialAvailable - 5, $balance->available_days);
    }

    /** @test */
    public function test_51_employee_show_displays_vacations_tab_and_balance()
    {
        $calcService = app(HrLeaveCalculationService::class);
        $calcService->getOrCreateBalance($this->activeEmployee, $this->vacationType, 2026);

        $response = $this->actingAs($this->adminUser)->get('/rrhh/employees/' . $this->activeEmployee->id);
        $response->assertStatus(200);
        $response->assertSee('Vacaciones y Licencias');
        $response->assertSee('Disponibles 2026');
    }
}

