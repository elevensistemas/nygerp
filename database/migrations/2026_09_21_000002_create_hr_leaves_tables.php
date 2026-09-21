<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateHrLeavesTables extends Migration
{
    public function up()
    {
        // 1. Tipos de Licencias / Ausencias
        Schema::create('hr_leave_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('code', 30)->unique();
            $table->text('description')->nullable();
            $table->enum('category', [
                'vacaciones',
                'medica',
                'examen_estudio',
                'maternidad_paternidad',
                'accidente',
                'personal_con_goce',
                'personal_sin_goce',
                'duelo',
                'matrimonio',
                'otro'
            ])->default('otro');
            $table->decimal('days_allowed_per_year', 5, 1)->nullable(); // Días fijados por año (si aplica)
            $table->enum('calculation_unit', ['corridos', 'habiles'])->default('habiles');
            $table->boolean('deducts_from_balance')->default(false); // Si descuenta de saldo de vacaciones u otro
            $table->boolean('requires_attachment')->default(false);  // Requiere certificado médico / comprobante
            $table->boolean('requires_approval')->default(true);
            $table->boolean('allows_half_day')->default(false);
            $table->integer('min_advance_days')->default(0);        // Anticipación mínima en días
            $table->string('color', 20)->default('#ffc107');        // Color identificador en calendario
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 2. Saldos de Licencias por Empleado y Período
        Schema::create('hr_leave_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('hr_employees')->cascadeOnDelete();
            $table->foreignId('leave_type_id')->constrained('hr_leave_types')->cascadeOnDelete();
            $table->integer('period_year'); // Ej: 2026
            $table->decimal('assigned_days', 6, 1)->default(0);
            $table->decimal('used_days', 6, 1)->default(0);
            $table->decimal('transferred_days', 6, 1)->default(0); // Días arrastrados de período anterior
            $table->decimal('pending_days', 6, 1)->default(0);     // Días solicitados pero aún no aprobados
            $table->decimal('available_days', 6, 1)->default(0);   // Saldo neto disponible
            $table->date('expiration_date')->nullable();           // Fecha de caducidad del saldo
            $table->timestamps();

            $table->unique(['employee_id', 'leave_type_id', 'period_year'], 'hr_employee_leave_period_unique');
        });

        // 3. Solicitudes de Licencia
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
            $table->enum('status', [
                'borrador',
                'pendiente',
                'aprobada',
                'rechazada',
                'cancelada',
                'finalizada'
            ])->default('pendiente');

            // Flujo de aprobación
            $table->foreignId('current_approver_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('approved_at')->nullable();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('rejected_at')->nullable();
            $table->text('rejection_reason')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });

        // 4. Auditoría de Ajustes de Saldo
        Schema::create('hr_leave_balance_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('hr_employees')->cascadeOnDelete();
            $table->foreignId('leave_type_id')->constrained('hr_leave_types')->cascadeOnDelete();
            $table->integer('period_year');
            $table->decimal('previous_balance', 6, 1);
            $table->decimal('new_balance', 6, 1);
            $table->decimal('difference', 6, 1);
            $table->text('reason');
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('hr_leave_balance_adjustments');
        Schema::dropIfExists('hr_leave_requests');
        Schema::dropIfExists('hr_leave_balances');
        Schema::dropIfExists('hr_leave_types');
    }
}
