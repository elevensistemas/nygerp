<?php

namespace Tests\Feature\HR;

use App\Models\User;
use App\Models\HR\Department;
use App\Models\HR\Employee;
use App\Models\HR\LeaveType;
use App\Models\HR\Position;
use App\Models\HR\Branch;
use App\Models\HR\Agreement;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class HrPhase1Test extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Ejecutar migraciones en la base de datos de prueba aislada
        Artisan::call('migrate');
    }

    /** @test */
    public function test_00_testing_environment_uses_isolated_testing_database()
    {
        $currentDb = DB::connection()->getDatabaseName();
        $this->assertEquals('nygerp_testing', $currentDb, 'Tests must strictly run on nygerp_testing');
        $this->assertNotEquals('nygerp', $currentDb, 'Tests must never touch the development/production database');
    }

    /** @test */
    public function test_01_unauthenticated_user_is_redirected_to_login_when_accessing_hr()
    {
        $response = $this->get('/rrhh');
        $response->assertRedirect('/login');
    }

    /** @test */
    public function test_02_admin_user_can_access_hr_dashboard()
    {
        $admin = User::firstOrCreate(['email' => 'admin_test@test.com'], [
            'name' => 'Admin Test',
            'password' => Hash::make('password'),
            'role' => User::ROLE_ADMIN,
            'accepted_at' => now(),
        ]);

        $response = $this->actingAs($admin)->get('/rrhh');
        $response->assertStatus(200);
        $response->assertSee('Recursos Humanos');
        $response->assertSee('Colaboradores Activos');
    }

    /** @test */
    public function test_03_user_without_permission_is_blocked_with_403()
    {
        $regularUser = User::firstOrCreate(['email' => 'regular_test@test.com'], [
            'name' => 'Regular User',
            'password' => Hash::make('password'),
            'role' => User::ROLE_USER,
            'accepted_at' => now(),
        ]);

        $response = $this->actingAs($regularUser)->get('/rrhh');
        $response->assertStatus(403);
    }

    /** @test */
    public function test_04_active_employee_without_admin_role_is_blocked_from_admin_dashboard()
    {
        $empUser = User::firstOrCreate(['email' => 'employee_active@test.com'], [
            'name' => 'Active Employee User',
            'password' => Hash::make('password'),
            'role' => User::ROLE_USER,
            'accepted_at' => now(),
        ]);

        Employee::firstOrCreate(['user_id' => $empUser->id], [
            'file_number' => 'LEG-TEST-001',
            'first_name' => 'Juan',
            'last_name' => 'Perez',
            'dni' => '11111111',
            'hire_date' => now()->subYears(1)->toDateString(),
            'status' => 'activo',
        ]);

        $this->assertTrue($empUser->fresh()->isActiveHrEmployee());
        $this->assertFalse($empUser->fresh()->isHrAdmin());

        // Acceso directo a /rrhh bloqueado con 403
        $response = $this->actingAs($empUser)->get('/rrhh');
        $response->assertStatus(403);
    }

    /** @test */
    public function test_05_ex_employee_is_blocked_from_admin_dashboard()
    {
        $exEmpUser = User::firstOrCreate(['email' => 'employee_ex@test.com'], [
            'name' => 'Ex Employee User',
            'password' => Hash::make('password'),
            'role' => User::ROLE_USER,
            'accepted_at' => now(),
        ]);

        Employee::firstOrCreate(['user_id' => $exEmpUser->id], [
            'file_number' => 'LEG-TEST-002',
            'first_name' => 'Carlos',
            'last_name' => 'Gomez',
            'dni' => '22222222',
            'hire_date' => now()->subYears(3)->toDateString(),
            'termination_date' => now()->subMonths(2)->toDateString(),
            'status' => 'egresado',
        ]);

        $this->assertTrue($exEmpUser->fresh()->isExEmployee());
        $this->assertFalse($exEmpUser->fresh()->isActiveHrEmployee());
        $this->assertFalse($exEmpUser->fresh()->isHrAdmin());

        // Acceso directo bloqueado con 403
        $response = $this->actingAs($exEmpUser)->get('/rrhh');
        $response->assertStatus(403);
    }

    /** @test */
    public function test_06_admin_user_sees_hr_menu_in_sidebar()
    {
        $admin = User::firstOrCreate(['email' => 'admin_menu_test@test.com'], [
            'name' => 'Admin Menu Test',
            'password' => Hash::make('password'),
            'role' => User::ROLE_ADMIN,
            'accepted_at' => now(),
        ]);

        $response = $this->actingAs($admin)->get('/');
        $response->assertStatus(200);
        $response->assertSee('Recursos Humanos');
        $response->assertSee('Panel RR. HH.');
    }

    /** @test */
    public function test_07_active_employee_without_admin_role_does_not_see_hr_menu_in_sidebar()
    {
        $empUser = User::firstOrCreate(['email' => 'employee_menu_active@test.com'], [
            'name' => 'Employee Menu User',
            'password' => Hash::make('password'),
            'role' => User::ROLE_USER,
            'accepted_at' => now(),
        ]);

        Employee::firstOrCreate(['user_id' => $empUser->id], [
            'file_number' => 'LEG-TEST-003',
            'first_name' => 'Maria',
            'last_name' => 'Lopez',
            'dni' => '33333333',
            'hire_date' => now()->subMonths(6)->toDateString(),
            'status' => 'activo',
        ]);

        $response = $this->actingAs($empUser)->get('/');
        $response->assertStatus(200);
        $response->assertDontSee('Panel RR. HH.');
    }

    /** @test */
    public function test_08_ex_employee_does_not_see_hr_menu_in_sidebar()
    {
        $exEmpUser = User::firstOrCreate(['email' => 'employee_menu_ex@test.com'], [
            'name' => 'Ex Employee Menu User',
            'password' => Hash::make('password'),
            'role' => User::ROLE_USER,
            'accepted_at' => now(),
        ]);

        Employee::firstOrCreate(['user_id' => $exEmpUser->id], [
            'file_number' => 'LEG-TEST-004',
            'first_name' => 'Pedro',
            'last_name' => 'Sosa',
            'dni' => '44444444',
            'hire_date' => now()->subYears(2)->toDateString(),
            'status' => 'egresado',
        ]);

        $response = $this->actingAs($exEmpUser)->get('/');
        $response->assertStatus(200);
        $response->assertDontSee('Panel RR. HH.');
    }

    /** @test */
    public function test_09_user_without_permission_does_not_see_hr_menu_in_sidebar()
    {
        $regularUser = User::firstOrCreate(['email' => 'regular_menu_test@test.com'], [
            'name' => 'Regular Menu User',
            'password' => Hash::make('password'),
            'role' => User::ROLE_USER,
            'accepted_at' => now(),
        ]);

        $response = $this->actingAs($regularUser)->get('/');
        $response->assertStatus(200);
        $response->assertDontSee('Panel RR. HH.');
    }

    /** @test */
    public function test_10_hr_dashboard_renders_with_empty_database_and_no_kpi_errors()
    {
        $admin = User::firstOrCreate(['email' => 'admin_empty_test@test.com'], [
            'name' => 'Admin Empty Test',
            'password' => Hash::make('password'),
            'role' => User::ROLE_ADMIN,
            'accepted_at' => now(),
        ]);

        $response = $this->actingAs($admin)->get('/rrhh');
        $response->assertStatus(200);
        // Debe renderizar indicadores en 0 sin errores
        $response->assertSee('0');
        $response->assertSee('No hay solicitudes de licencia pendientes');
        $response->assertSee('No hay comunicados publicados');
    }

    /** @test */
    public function test_11_hr_routes_are_isolated_and_protected()
    {
        $this->assertTrue(app('router')->has('rrhh.dashboard'));
        $route = app('router')->getRoutes()->getByName('rrhh.dashboard');
        $this->assertNotNull($route);
        $this->assertStringContainsString('rrhh', $route->uri());
    }

    /** @test */
    public function test_12_existing_core_routes_continue_working_normally()
    {
        $admin = User::firstOrCreate(['email' => 'admin_existing_test@test.com'], [
            'name' => 'Admin Existing Test',
            'password' => Hash::make('password'),
            'role' => User::ROLE_ADMIN,
            'accepted_at' => now(),
        ]);

        $response = $this->actingAs($admin)->get('/');
        $response->assertStatus(200);

        $responseDocs = $this->actingAs($admin)->get('/documents?scope=sale');
        $responseDocs->assertStatus(200);
    }

    /** @test */
    public function test_13_hr_tables_exist_and_follow_naming_conventions()
    {
        $tables = [
            'hr_departments',
            'hr_positions',
            'hr_branches',
            'hr_agreements',
            'hr_employees',
            'hr_leave_types',
            'hr_leave_balances',
            'hr_leave_requests',
            'hr_leave_balance_adjustments',
            'hr_document_types',
            'hr_documents',
            'hr_document_assignments',
            'hr_employee_files',
            'hr_onboardings',
            'hr_onboarding_tasks',
            'hr_templates',
            'hr_profile_requests',
            'hr_bulletins',
            'hr_bulletin_reads',
            'hr_events',
            'hr_notifications',
            'hr_audit_logs',
        ];

        foreach ($tables as $table) {
            $this->assertTrue(Schema::hasTable($table), "Table {$table} should exist");
        }
    }

    /** @test */
    public function test_14_hr_migrations_can_be_rolled_back_cleanly_in_test_database()
    {
        // Revertir migraciones de HR
        $exitCode = Artisan::call('migrate:rollback', ['--step' => 9]);
        $this->assertEquals(0, $exitCode);

        $this->assertFalse(Schema::hasTable('hr_audit_logs'));
        $this->assertFalse(Schema::hasTable('hr_employees'));
        $this->assertFalse(Schema::hasTable('hr_departments'));

        // Volver a aplicar migraciones para dejar la base de pruebas limpia
        $remigrateExitCode = Artisan::call('migrate');
        $this->assertEquals(0, $remigrateExitCode);
        $this->assertTrue(Schema::hasTable('hr_employees'));
    }
}

