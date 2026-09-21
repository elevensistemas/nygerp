<?php

namespace Database\Seeders;

use App\Models\HR\Agreement;
use App\Models\HR\Branch;
use App\Models\HR\Department;
use App\Models\HR\DocumentType;
use App\Models\HR\Employee;
use App\Models\HR\LeaveType;
use App\Models\HR\Position;
use App\Models\User;
use App\Services\HR\HrAuditService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class HrSeeder extends Seeder
{
    public function run()
    {
        // 1. Áreas / Departamentos
        $deptAdmin = Department::updateOrCreate(['code' => 'ADM'], [
            'name' => 'Administración & Finanzas',
            'description' => 'Área contable, tesorería y facturación.',
            'is_active' => true,
        ]);

        $deptLog = Department::updateOrCreate(['code' => 'LOG'], [
            'name' => 'Operaciones & Logística',
            'description' => 'Gestión de depósitos, distribución y ruteo.',
            'is_active' => true,
        ]);

        $deptTraf = Department::updateOrCreate(['code' => 'TRAF'], [
            'name' => 'Tráfico & Flota',
            'description' => 'Supervisión de choferes y unidades de transporte.',
            'is_active' => true,
        ]);

        $deptHr = Department::updateOrCreate(['code' => 'RRHH'], [
            'name' => 'Recursos Humanos',
            'description' => 'Gestión del talento, liquidación y personal.',
            'is_active' => true,
        ]);

        // 2. Puestos de Trabajo
        $posHr = Position::updateOrCreate(['code' => 'HR-MGR'], [
            'name' => 'Responsable de Recursos Humanos',
            'department_id' => $deptHr->id,
            'description' => 'Coordinación integral del área de personal y relaciones laborales.',
            'is_active' => true,
        ]);

        Position::updateOrCreate(['code' => 'LOG-OP'], [
            'name' => 'Operador de Logística',
            'department_id' => $deptLog->id,
            'description' => 'Coordinación y despacho de cargas.',
            'is_active' => true,
        ]);

        Position::updateOrCreate(['code' => 'TRAF-SUP'], [
            'name' => 'Supervisor de Tráfico',
            'department_id' => $deptTraf->id,
            'description' => 'Planificación y seguimiento de rutas operativas.',
            'is_active' => true,
        ]);

        // 3. Sucursales / Bases
        $branchAmba = Branch::updateOrCreate(['code' => 'B-AMBA'], [
            'name' => 'Base Central AMBA',
            'address' => 'Av. de Circunvalación 1200',
            'city' => 'Buenos Aires',
            'province' => 'Buenos Aires',
            'is_active' => true,
        ]);

        Branch::updateOrCreate(['code' => 'B-CBA'], [
            'name' => 'Depósito Córdoba',
            'address' => 'Ruta 9 Km 690',
            'city' => 'Córdoba Capital',
            'province' => 'Córdoba',
            'is_active' => true,
        ]);

        // 4. Convenio Colectivo
        $agrTransporte = Agreement::updateOrCreate(['code' => 'CCT-40-89'], [
            'name' => 'CCT 40/89 - Transporte Automotor de Cargas',
            'description' => 'Convenio estándar para el sector de logística y transporte de mercaderías.',
            'vacation_scale' => [
                ['min_years' => 0, 'max_years' => 4, 'days' => 14],
                ['min_years' => 5, 'max_years' => 9, 'days' => 21],
                ['min_years' => 10, 'max_years' => 19, 'days' => 28],
                ['min_years' => 20, 'max_years' => 999, 'days' => 35],
            ],
            'is_active' => true,
        ]);

        // 5. Tipos de Licencias
        LeaveType::updateOrCreate(['code' => 'VAC'], [
            'name' => 'Vacaciones Anuales',
            'category' => 'vacaciones',
            'calculation_unit' => 'corridos',
            'deducts_from_balance' => true,
            'requires_attachment' => false,
            'requires_approval' => true,
            'allows_half_day' => false,
            'min_advance_days' => 15,
            'color' => '#ffc107',
            'is_active' => true,
        ]);

        LeaveType::updateOrCreate(['code' => 'MED'], [
            'name' => 'Licencia Médica / Enfermedad',
            'category' => 'medica',
            'calculation_unit' => 'corridos',
            'deducts_from_balance' => false,
            'requires_attachment' => true,
            'requires_approval' => true,
            'allows_half_day' => true,
            'min_advance_days' => 0,
            'color' => '#dc3545',
            'is_active' => true,
        ]);

        LeaveType::updateOrCreate(['code' => 'EST'], [
            'name' => 'Examen / Estudio Universitario',
            'category' => 'examen_estudio',
            'days_allowed_per_year' => 10,
            'calculation_unit' => 'habiles',
            'deducts_from_balance' => false,
            'requires_attachment' => true,
            'requires_approval' => true,
            'allows_half_day' => false,
            'min_advance_days' => 2,
            'color' => '#0d6efd',
            'is_active' => true,
        ]);

        LeaveType::updateOrCreate(['code' => 'DUE'], [
            'name' => 'Duelo Familiar',
            'category' => 'duelo',
            'days_allowed_per_year' => 3,
            'calculation_unit' => 'corridos',
            'deducts_from_balance' => false,
            'requires_attachment' => false,
            'requires_approval' => true,
            'allows_half_day' => false,
            'min_advance_days' => 0,
            'color' => '#6c757d',
            'is_active' => true,
        ]);

        // 6. Tipos de Documentos
        DocumentType::updateOrCreate(['code' => 'REC-SUELDO'], [
            'name' => 'Recibo de Sueldo',
            'requires_signature' => true,
            'is_sensitive' => true,
            'allow_ex_employee_access' => true,
            'is_active' => true,
        ]);

        DocumentType::updateOrCreate(['code' => 'CONTRATO'], [
            'name' => 'Contrato de Trabajo',
            'requires_signature' => true,
            'is_sensitive' => true,
            'allow_ex_employee_access' => true,
            'is_active' => true,
        ]);

        DocumentType::updateOrCreate(['code' => 'POL-SEG'], [
            'name' => 'Reglamento y Política de Seguridad',
            'requires_signature' => true,
            'is_sensitive' => false,
            'allow_ex_employee_access' => false,
            'is_active' => true,
        ]);

        // 7. Empleado base vinculado a usuario administrador (si existe)
        $adminUser = User::where('email', 'admin@admin.com')->orWhere('email', 'admin@eleven.com')->first();
        if ($adminUser) {
            Employee::updateOrCreate(['dni' => '30111222'], [
                'user_id' => $adminUser->id,
                'file_number' => 'EMP-0001',
                'first_name' => 'Administrador',
                'last_name' => 'NyG',
                'dni' => '30111222',
                'cuil' => '20-30111222-9',
                'gender' => 'M',
                'birth_date' => Carbon::create(1988, 5, 14),
                'nationality' => 'Argentina',
                'marital_status' => 'Soltero/a',
                'phone' => '+54 11 4000-0000',
                'personal_email' => $adminUser->email,
                'work_email' => 'rrhh@nygtransporte.com.ar',
                'address' => 'Av. Principal 100',
                'city' => 'CABA',
                'province' => 'Buenos Aires',
                'postal_code' => '1000',
                'department_id' => $deptHr->id,
                'position_id' => $posHr->id,
                'branch_id' => $branchAmba->id,
                'agreement_id' => $agrTransporte->id,
                'hire_date' => Carbon::create(2020, 1, 1),
                'contract_type' => 'indeterminado',
                'status' => 'activo',
                'notes' => 'Responsable principal del sistema de Recursos Humanos.',
            ]);
        }

        // 8. Registro de auditoría inicial
        HrAuditService::log(
            'init',
            'System',
            null,
            null,
            ['status' => 'initialized'],
            'Inicialización de parámetros base del Módulo de Recursos Humanos (Fase 1).'
        );
    }
}
