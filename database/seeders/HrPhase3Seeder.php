<?php

namespace Database\Seeders;

use App\Models\HR\Agreement;
use App\Models\HR\Holiday;
use App\Models\HR\LeavePolicy;
use App\Models\HR\LeaveType;
use Illuminate\Database\Seeder;

class HrPhase3Seeder extends Seeder
{
    /**
     * Seeder específico e idempotente para la Fase 3 de Recursos Humanos.
     * NO debe ejecutarse automáticamente desde DatabaseSeeder.
     */
    public function run(): void
    {
        // 1. Tipos de Ausencias y Licencias Iniciales
        $leaveTypes = [
            [
                'code' => 'VAC',
                'name' => 'Vacaciones Ordinarias',
                'description' => 'Descanso anual remunerado reglamentario según antigüedad.',
                'category' => 'vacaciones',
                'days_allowed_per_year' => 14,
                'calculation_unit' => 'corridos',
                'deducts_from_balance' => true,
                'requires_attachment' => false,
                'requires_approval' => true,
                'allows_half_day' => false,
                'allows_negative_balance' => false,
                'requires_reason' => false,
                'min_advance_days' => 15,
                'display_order' => 1,
                'color' => '#0d6efd', // Azul primario
            ],
            [
                'code' => 'MED',
                'name' => 'Licencia Médica / Enfermedad',
                'description' => 'Ausencia por enfermedad inculpable o patología médica con certificado.',
                'category' => 'medica',
                'days_allowed_per_year' => null,
                'calculation_unit' => 'corridos',
                'deducts_from_balance' => false,
                'requires_attachment' => true,
                'requires_approval' => true,
                'allows_half_day' => true,
                'allows_negative_balance' => true,
                'requires_reason' => true,
                'min_advance_days' => 0,
                'display_order' => 2,
                'color' => '#dc3545', // Rojo
            ],
            [
                'code' => 'DIA_PERS',
                'name' => 'Día Personal / Trámites',
                'description' => 'Día para trámites personales o razones particulares con goce.',
                'category' => 'personal_con_goce',
                'days_allowed_per_year' => 3,
                'calculation_unit' => 'habiles',
                'deducts_from_balance' => true,
                'requires_attachment' => false,
                'requires_approval' => true,
                'allows_half_day' => true,
                'allows_negative_balance' => false,
                'requires_reason' => true,
                'min_advance_days' => 2,
                'display_order' => 3,
                'color' => '#6f42c1', // Púrpura
            ],
            [
                'code' => 'EST',
                'name' => 'Licencia por Estudio / Examen',
                'description' => 'Días para rendir exámenes en niveles secundarios, terciarios o universitarios.',
                'category' => 'examen_estudio',
                'days_allowed_per_year' => 10,
                'calculation_unit' => 'habiles',
                'deducts_from_balance' => true,
                'requires_attachment' => true,
                'requires_approval' => true,
                'allows_half_day' => false,
                'allows_negative_balance' => false,
                'requires_reason' => true,
                'min_advance_days' => 5,
                'display_order' => 4,
                'color' => '#0dcaf0', // Cian
            ],
            [
                'code' => 'MAT',
                'name' => 'Licencia por Maternidad',
                'description' => 'Licencia legal por maternidad de 90 días corridos.',
                'category' => 'maternidad_paternidad',
                'days_allowed_per_year' => 90,
                'calculation_unit' => 'corridos',
                'deducts_from_balance' => false,
                'requires_attachment' => true,
                'requires_approval' => true,
                'allows_half_day' => false,
                'allows_negative_balance' => false,
                'requires_reason' => false,
                'min_advance_days' => 30,
                'display_order' => 5,
                'color' => '#d63384', // Rosa
            ],
            [
                'code' => 'PAT',
                'name' => 'Licencia por Paternidad',
                'description' => 'Licencia por nacimiento de hijo.',
                'category' => 'maternidad_paternidad',
                'days_allowed_per_year' => 2,
                'calculation_unit' => 'corridos',
                'deducts_from_balance' => false,
                'requires_attachment' => true,
                'requires_approval' => true,
                'allows_half_day' => false,
                'allows_negative_balance' => false,
                'requires_reason' => false,
                'min_advance_days' => 0,
                'display_order' => 6,
                'color' => '#20c997', // Verde azulado
            ],
            [
                'code' => 'ART',
                'name' => 'Accidente Laboral / ART',
                'description' => 'Siniestro o enfermedad profesional cubierta por ART.',
                'category' => 'accidente',
                'days_allowed_per_year' => null,
                'calculation_unit' => 'corridos',
                'deducts_from_balance' => false,
                'requires_attachment' => true,
                'requires_approval' => true,
                'allows_half_day' => false,
                'allows_negative_balance' => true,
                'requires_reason' => true,
                'min_advance_days' => 0,
                'display_order' => 7,
                'color' => '#fd7e14', // Naranja
            ],
            [
                'code' => 'LIC_CG',
                'name' => 'Licencia Especial con Goce',
                'description' => 'Ausencia autorizada expresamente con goce de haberes.',
                'category' => 'personal_con_goce',
                'days_allowed_per_year' => null,
                'calculation_unit' => 'habiles',
                'deducts_from_balance' => false,
                'requires_attachment' => false,
                'requires_approval' => true,
                'allows_half_day' => true,
                'allows_negative_balance' => true,
                'requires_reason' => true,
                'min_advance_days' => 2,
                'display_order' => 8,
                'color' => '#198754', // Verde
            ],
            [
                'code' => 'LIC_SG',
                'name' => 'Licencia sin Goce de Haberes',
                'description' => 'Permiso especial extraordinario sin percepción de haberes.',
                'category' => 'personal_sin_goce',
                'days_allowed_per_year' => null,
                'calculation_unit' => 'corridos',
                'deducts_from_balance' => false,
                'requires_attachment' => false,
                'requires_approval' => true,
                'allows_half_day' => false,
                'allows_negative_balance' => true,
                'requires_reason' => true,
                'min_advance_days' => 15,
                'display_order' => 9,
                'color' => '#6c757d', // Gris
            ],
            [
                'code' => 'OTRA',
                'name' => 'Otra Ausencia / Causa Justificada',
                'description' => 'Otras causales no tipificadas (donación de sangre, citación judicial, etc.).',
                'category' => 'otro',
                'days_allowed_per_year' => null,
                'calculation_unit' => 'habiles',
                'deducts_from_balance' => false,
                'requires_attachment' => false,
                'requires_approval' => true,
                'allows_half_day' => true,
                'allows_negative_balance' => true,
                'requires_reason' => true,
                'min_advance_days' => 1,
                'display_order' => 10,
                'color' => '#343a40', // Carbón
            ],
        ];

        foreach ($leaveTypes as $data) {
            LeaveType::updateOrCreate(['code' => $data['code']], $data);
        }

        // 2. Feriados Diferenciados (Fijos Recurrentes vs Móviles 2026)
        $holidays = [
            // Feriados Inamovibles de Fecha Fija (is_recurring = true)
            ['name' => 'Año Nuevo', 'date' => '2026-01-01', 'is_recurring' => true, 'type' => 'national'],
            ['name' => 'Día Nacional de la Memoria por la Verdad y la Justicia', 'date' => '2026-03-24', 'is_recurring' => true, 'type' => 'national'],
            ['name' => 'Día del Veterano y de los Caídos en la Guerra de Malvinas', 'date' => '2026-04-02', 'is_recurring' => true, 'type' => 'national'],
            ['name' => 'Día del Trabajador', 'date' => '2026-05-01', 'is_recurring' => true, 'type' => 'national'],
            ['name' => 'Día de la Revolución de Mayo', 'date' => '2026-05-25', 'is_recurring' => true, 'type' => 'national'],
            ['name' => 'Paso a la Inmortalidad del Gral. Manuel Belgrano', 'date' => '2026-06-20', 'is_recurring' => true, 'type' => 'national'],
            ['name' => 'Día de la Independencia', 'date' => '2026-07-09', 'is_recurring' => true, 'type' => 'national'],
            ['name' => 'Inmaculada Concepción de María', 'date' => '2026-12-08', 'is_recurring' => true, 'type' => 'national'],
            ['name' => 'Navidad', 'date' => '2026-12-25', 'is_recurring' => true, 'type' => 'national'],

            // Feriados Móviles / Trasladables / Religiosos 2026 (is_recurring = false)
            ['name' => 'Carnaval (Lunes)', 'date' => '2026-02-16', 'is_recurring' => false, 'type' => 'national'],
            ['name' => 'Carnaval (Martes)', 'date' => '2026-02-17', 'is_recurring' => false, 'type' => 'national'],
            ['name' => 'Viernes Santo', 'date' => '2026-04-03', 'is_recurring' => false, 'type' => 'national'],
            ['name' => 'Paso a la Inmortalidad del Gral. Don Martín Miguel de Güemes', 'date' => '2026-06-15', 'is_recurring' => false, 'type' => 'national'],
            ['name' => 'Paso a la Inmortalidad del Gral. José de San Martín', 'date' => '2026-08-17', 'is_recurring' => false, 'type' => 'national'],
            ['name' => 'Día del Respeto a la Diversidad Cultural', 'date' => '2026-10-12', 'is_recurring' => false, 'type' => 'national'],
            ['name' => 'Día de la Soberanía Nacional', 'date' => '2026-11-23', 'is_recurring' => false, 'type' => 'national'],
        ];

        foreach ($holidays as $h) {
            Holiday::updateOrCreate(
                ['date' => $h['date']],
                [
                    'name' => $h['name'],
                    'year' => (int) substr($h['date'], 0, 4),
                    'type' => $h['type'],
                    'is_recurring' => $h['is_recurring'],
                    'is_active' => true,
                ]
            );
        }

        // 3. Políticas de Vacaciones Escalonadas (Convenio General / LCT)
        $vacationType = LeaveType::where('code', 'VAC')->first();

        if ($vacationType) {
            $standardScales = [
                ['from' => 0, 'to' => 4, 'days' => 14, 'name' => 'Hasta 5 años de antigüedad (14 días)'],
                ['from' => 5, 'to' => 9, 'days' => 21, 'name' => 'De 5 a 10 años de antigüedad (21 días)'],
                ['from' => 10, 'to' => 19, 'days' => 28, 'name' => 'De 10 a 20 años de antigüedad (28 días)'],
                ['from' => 20, 'to' => null, 'days' => 35, 'name' => 'Más de 20 años de antigüedad (35 días)'],
            ];

            foreach ($standardScales as $scale) {
                LeavePolicy::updateOrCreate(
                    [
                        'leave_type_id' => $vacationType->id,
                        'agreement_id' => null,
                        'seniority_years_from' => $scale['from'],
                    ],
                    [
                        'name' => $scale['name'],
                        'seniority_years_to' => $scale['to'],
                        'days_granted' => $scale['days'],
                        'allow_transfer' => true,
                        'max_transferred_days' => 7,
                        'transfer_expiration_months' => 6,
                        'is_active' => true,
                    ]
                );
            }
        }
    }
}
