<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. hr_departments
        if (!Schema::hasTable('hr_departments')) {
            Schema::create('hr_departments', function (Blueprint $table) {
                $table->id();
                $table->string('name', 120);
                $table->string('code', 30)->unique();
                $table->text('description')->nullable();
                $table->foreignId('parent_id')->nullable()->constrained('hr_departments')->nullOnDelete();
                $table->foreignId('manager_id')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        // 2. hr_positions
        if (!Schema::hasTable('hr_positions')) {
            Schema::create('hr_positions', function (Blueprint $table) {
                $table->id();
                $table->string('name', 120);
                $table->string('code', 30)->unique();
                $table->foreignId('department_id')->nullable()->constrained('hr_departments')->nullOnDelete();
                $table->text('description')->nullable();
                $table->text('requirements')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        // 3. hr_branches
        if (!Schema::hasTable('hr_branches')) {
            Schema::create('hr_branches', function (Blueprint $table) {
                $table->id();
                $table->string('name', 120);
                $table->string('code', 30)->unique();
                $table->string('address', 255)->nullable();
                $table->string('city', 100)->nullable();
                $table->string('province', 100)->nullable();
                $table->string('phone', 50)->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        // 4. hr_agreements
        if (!Schema::hasTable('hr_agreements')) {
            Schema::create('hr_agreements', function (Blueprint $table) {
                $table->id();
                $table->string('name', 120);
                $table->string('code', 30)->unique();
                $table->text('description')->nullable();
                $table->json('vacation_scale')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        // 5. hr_employees
        if (!Schema::hasTable('hr_employees')) {
            Schema::create('hr_employees', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('file_number', 30)->unique();
                $table->string('first_name', 100);
                $table->string('last_name', 100);
                $table->string('dni', 20)->unique();
                $table->string('cuil', 25)->nullable()->unique();
                $table->enum('gender', ['M', 'F', 'X', 'otro'])->default('otro');
                $table->date('birth_date')->nullable();
                $table->string('nationality', 60)->default('Argentina');
                $table->string('marital_status', 40)->nullable();
                $table->string('phone', 50)->nullable();
                $table->string('personal_email', 150)->nullable();
                $table->string('work_email', 150)->nullable();
                $table->string('address', 255)->nullable();
                $table->string('city', 100)->nullable();
                $table->string('province', 100)->nullable();
                $table->string('postal_code', 20)->nullable();
                $table->string('emergency_contact_name', 150)->nullable();
                $table->string('emergency_contact_phone', 50)->nullable();
                $table->string('emergency_contact_relationship', 60)->nullable();
                $table->foreignId('department_id')->nullable()->constrained('hr_departments')->nullOnDelete();
                $table->foreignId('position_id')->nullable()->constrained('hr_positions')->nullOnDelete();
                $table->foreignId('branch_id')->nullable()->constrained('hr_branches')->nullOnDelete();
                $table->foreignId('manager_id')->nullable()->constrained('hr_employees')->nullOnDelete();
                $table->foreignId('agreement_id')->nullable()->constrained('hr_agreements')->nullOnDelete();
                $table->date('hire_date');
                $table->date('vacation_seniority_date')->nullable();
                $table->date('probation_end_date')->nullable();
                $table->enum('contract_type', ['indeterminado', 'plazo_fijo', 'pasantia', 'eventual', 'otro'])->default('indeterminado');
                $table->enum('status', ['activo', 'licencia', 'suspendido', 'en_onboarding', 'egresado'])->default('activo');
                $table->date('termination_date')->nullable();
                $table->string('termination_reason', 255)->nullable();
                $table->decimal('salary', 14, 2)->nullable();
                $table->string('avatar_path', 255)->nullable();
                $table->text('notes')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        // 6. hr_leave_types
        if (!Schema::hasTable('hr_leave_types')) {
            Schema::create('hr_leave_types', function (Blueprint $table) {
                $table->id();
                $table->string('name', 120);
                $table->string('code', 30)->unique();
                $table->text('description')->nullable();
                $table->enum('category', [
                    'vacaciones', 'medica', 'examen_estudio', 'maternidad_paternidad',
                    'accidente', 'personal_con_goce', 'personal_sin_goce', 'duelo', 'matrimonio', 'otro'
                ])->default('otro');
                $table->decimal('days_allowed_per_year', 5, 1)->nullable();
                $table->enum('calculation_unit', ['corridos', 'habiles'])->default('habiles');
                $table->boolean('deducts_from_balance')->default(false);
                $table->boolean('requires_attachment')->default(false);
                $table->boolean('requires_approval')->default(true);
                $table->boolean('allows_half_day')->default(false);
                $table->integer('min_advance_days')->default(0);
                $table->string('color', 20)->default('#ffc107');
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        // 7. hr_leave_balances
        if (!Schema::hasTable('hr_leave_balances')) {
            Schema::create('hr_leave_balances', function (Blueprint $table) {
                $table->id();
                $table->foreignId('employee_id')->constrained('hr_employees')->cascadeOnDelete();
                $table->foreignId('leave_type_id')->constrained('hr_leave_types')->cascadeOnDelete();
                $table->integer('period_year');
                $table->decimal('assigned_days', 6, 1)->default(0);
                $table->decimal('used_days', 6, 1)->default(0);
                $table->decimal('transferred_days', 6, 1)->default(0);
                $table->decimal('pending_days', 6, 1)->default(0);
                $table->decimal('available_days', 6, 1)->default(0);
                $table->date('expiration_date')->nullable();
                $table->timestamps();
                $table->unique(['employee_id', 'leave_type_id', 'period_year'], 'hr_emp_leave_per_unique');
            });
        }

        // 8. hr_leave_requests
        if (!Schema::hasTable('hr_leave_requests')) {
            Schema::create('hr_leave_requests', function (Blueprint $table) {
                $table->id();
                $table->foreignId('employee_id')->constrained('hr_employees')->cascadeOnDelete();
                $table->foreignId('leave_type_id')->constrained('hr_leave_types')->cascadeOnDelete();
                $table->date('date_from');
                $table->date('date_to');
                $table->decimal('days_count', 5, 1);
                $table->boolean('is_half_day')->default(false);
                $table->enum('half_day_type', ['manana', 'tarde'])->nullable();
                $table->text('reason')->nullable();
                $table->text('notes')->nullable();
                $table->string('attachment_path', 255)->nullable();
                $table->enum('status', ['borrador', 'pendiente', 'aprobada', 'rechazada', 'cancelada', 'finalizada'])->default('pendiente');
                $table->foreignId('current_approver_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->dateTime('approved_at')->nullable();
                $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
                $table->dateTime('rejected_at')->nullable();
                $table->text('rejection_reason')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }
    }

    public function down(): void
    {
        // Reparación idempotente.
    }
};
