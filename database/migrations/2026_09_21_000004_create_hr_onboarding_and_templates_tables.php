<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateHrOnboardingAndTemplatesTables extends Migration
{
    public function up()
    {
        // 1. Plantillas de Formularios Dinámicos
        Schema::create('hr_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('code', 50)->unique();
            $table->enum('category', ['onboarding', 'tramite', 'evaluacion', 'declaracion_jurada', 'otro'])->default('onboarding');
            $table->text('description')->nullable();
            $table->json('fields_schema')->nullable(); // Configuración de campos (text, date, select, file, etc.)
            $table->foreignId('department_id')->nullable()->constrained('hr_departments')->nullOnDelete();
            $table->foreignId('position_id')->nullable()->constrained('hr_positions')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->integer('version')->default(1);
            $table->timestamps();
        });

        // 2. Procesos de Onboarding
        Schema::create('hr_onboardings', function (Blueprint $table) {
            $table->id();
            $table->string('candidate_name', 120);
            $table->string('candidate_email', 150);
            $table->string('candidate_phone', 50)->nullable();
            $table->string('candidate_dni', 20)->nullable();
            $table->string('invitation_token', 64)->unique(); // Token seguro de acceso previo
            $table->dateTime('invited_at')->nullable();
            $table->dateTime('expires_at')->nullable();
            $table->date('target_hire_date'); // Fecha estimada de ingreso
            
            $table->foreignId('department_id')->nullable()->constrained('hr_departments')->nullOnDelete();
            $table->foreignId('position_id')->nullable()->constrained('hr_positions')->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('hr_branches')->nullOnDelete();
            $table->foreignId('manager_id')->nullable()->constrained('hr_employees')->nullOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained('hr_employees')->nullOnDelete(); // Convertido a empleado

            $table->enum('status', [
                'invitacion_pendiente',
                'invitado',
                'en_proceso',
                'documentacion_pendiente',
                'en_revision',
                'observado',
                'aprobado',
                'completado',
                'cancelado'
            ])->default('invitacion_pendiente');

            $table->integer('progress_percentage')->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // 3. Tareas y Requisitos de Onboarding
        Schema::create('hr_onboarding_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('onboarding_id')->constrained('hr_onboardings')->cascadeOnDelete();
            $table->string('title', 150);
            $table->text('description')->nullable();
            $table->enum('task_type', ['upload_file', 'fill_form', 'read_document', 'accept_policy'])->default('upload_file');
            $table->enum('category', [
                'dni_frente_dorso',
                'constancia_cuil',
                'alta_temprana_afip',
                'apto_medico',
                'cbu_banco',
                'declaracion_jurada',
                'curriculum_vitae',
                'reglamento_interno',
                'politica_seguridad',
                'contrato',
                'otro'
            ])->default('otro');
            $table->boolean('is_required')->default(true);
            $table->date('due_date')->nullable();
            
            $table->enum('status', [
                'pendiente',
                'completada',
                'en_revision',
                'observada',
                'aprobada',
                'rechazada'
            ])->default('pendiente');

            // Respuestas / Archivos
            $table->string('file_path', 255)->nullable();
            $table->string('file_name', 200)->nullable();
            $table->json('form_data')->nullable(); // Respuestas si es formulario
            $table->text('observation_notes')->nullable();

            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('reviewed_at')->nullable();
            $table->timestamps();
        });

        // 4. Solicitudes de Modificación de Datos Personales (Autogestión)
        Schema::create('hr_profile_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('hr_employees')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('field_name', 80); // phone, address, emergency_contact, personal_email, etc.
            $table->text('old_value')->nullable();
            $table->text('new_value');
            $table->enum('status', ['pendiente', 'aprobado', 'rechazado'])->default('pendiente');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('reviewed_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('hr_profile_requests');
        Schema::dropIfExists('hr_onboarding_tasks');
        Schema::dropIfExists('hr_onboardings');
        Schema::dropIfExists('hr_templates');
    }
}
