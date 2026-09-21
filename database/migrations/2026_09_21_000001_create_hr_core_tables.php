<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateHrCoreTables extends Migration
{
    public function up()
    {
        // 1. Áreas / Departamentos
        Schema::create('hr_departments', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('code', 30)->unique();
            $table->text('description')->nullable();
            $table->foreignId('parent_id')->nullable()->constrained('hr_departments')->nullOnDelete();
            $table->foreignId('manager_id')->nullable(); // Se enlazará con hr_employees
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 2. Puestos / Cargos
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

        // 3. Sucursales / Bases Operativas
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

        // 4. Convenios Colectivos / Políticas de Vacaciones
        Schema::create('hr_agreements', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('code', 30)->unique();
            $table->text('description')->nullable();
            $table->json('vacation_scale')->nullable(); // Escala de días según antigüedad
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 5. Empleados / Colaboradores
        Schema::create('hr_employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('file_number', 30)->unique(); // Número de legajo
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('dni', 20)->unique();
            $table->string('cuil', 25)->nullable()->unique();
            $table->enum('gender', ['M', 'F', 'X', 'otro'])->default('otro');
            $table->date('birth_date')->nullable();
            $table->string('nationality', 60)->default('Argentina');
            $table->string('marital_status', 40)->nullable();
            
            // Contacto
            $table->string('phone', 50)->nullable();
            $table->string('personal_email', 150)->nullable();
            $table->string('work_email', 150)->nullable();
            $table->string('address', 255)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('province', 100)->nullable();
            $table->string('postal_code', 20)->nullable();
            
            // Emergencia
            $table->string('emergency_contact_name', 150)->nullable();
            $table->string('emergency_contact_phone', 50)->nullable();
            $table->string('emergency_contact_relationship', 60)->nullable();

            // Datos Laborales
            $table->foreignId('department_id')->nullable()->constrained('hr_departments')->nullOnDelete();
            $table->foreignId('position_id')->nullable()->constrained('hr_positions')->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('hr_branches')->nullOnDelete();
            $table->foreignId('manager_id')->nullable()->constrained('hr_employees')->nullOnDelete();
            $table->foreignId('agreement_id')->nullable()->constrained('hr_agreements')->nullOnDelete();
            
            $table->date('hire_date');
            $table->date('probation_end_date')->nullable();
            $table->enum('contract_type', ['indeterminado', 'plazo_fijo', 'pasantia', 'eventual', 'otro'])->default('indeterminado');
            $table->enum('status', ['activo', 'licencia', 'suspendido', 'en_onboarding', 'egresado'])->default('activo');
            $table->date('termination_date')->nullable();
            $table->string('termination_reason', 255)->nullable();
            $table->decimal('salary', 14, 2)->nullable();
            
            // Multimedia & notas
            $table->string('avatar_path', 255)->nullable();
            $table->text('notes')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        // Relación diferida manager en departments
        Schema::table('hr_departments', function (Blueprint $table) {
            $table->foreign('manager_id')->references('id')->on('hr_employees')->nullOnDelete();
        });
    }

    public function down()
    {
        Schema::table('hr_departments', function (Blueprint $table) {
            $table->dropForeign(['manager_id']);
        });
        Schema::dropIfExists('hr_employees');
        Schema::dropIfExists('hr_agreements');
        Schema::dropIfExists('hr_branches');
        Schema::dropIfExists('hr_positions');
        Schema::dropIfExists('hr_departments');
    }
}
