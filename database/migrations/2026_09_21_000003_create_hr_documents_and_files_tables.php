<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateHrDocumentsAndFilesTables extends Migration
{
    public function up()
    {
        // 1. Tipos de Documentos
        Schema::create('hr_document_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('code', 30)->unique();
            $table->boolean('requires_signature')->default(false);
            $table->boolean('is_sensitive')->default(false);
            $table->boolean('allow_ex_employee_access')->default(false); // Si un egresado puede descargarlo
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 2. Biblioteca de Documentos y Distribuciones
        Schema::create('hr_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_type_id')->constrained('hr_document_types')->cascadeOnDelete();
            $table->string('title', 200);
            $table->text('description')->nullable();
            
            // Archivo
            $table->string('file_path', 255);
            $table->string('file_name', 200);
            $table->unsignedBigInteger('file_size')->default(0);
            $table->string('file_hash', 64); // SHA-256
            $table->string('file_mime', 100)->nullable();

            // Alcance de distribución
            $table->enum('scope', ['individual', 'area', 'sucursal', 'empresa'])->default('individual');
            $table->foreignId('department_id')->nullable()->constrained('hr_departments')->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('hr_branches')->nullOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained('hr_employees')->nullOnDelete();

            // Fechas y estado
            $table->dateTime('published_at')->nullable();
            $table->date('due_date')->nullable();
            $table->integer('version')->default(1);
            $table->enum('status', ['borrador', 'publicado', 'archivado', 'anulado'])->default('publicado');

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        // 3. Asignaciones de Documento y Firmas / Aceptaciones Electrónicas
        Schema::create('hr_document_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained('hr_documents')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('hr_employees')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->enum('status', [
                'pendiente_lectura',
                'pendiente_firma',
                'firmado_conforme',
                'firmado_disconforme',
                'vencido',
                'anulado'
            ])->default('pendiente_lectura');

            $table->dateTime('viewed_at')->nullable();
            $table->dateTime('signed_at')->nullable();
            $table->enum('response_type', ['conforme', 'disconforme', 'leido'])->nullable();
            $table->text('comments')->nullable();

            // Evidencia de auditoría y no repudio
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('signed_file_hash', 64)->nullable();
            $table->boolean('allow_ex_employee')->default(false);

            $table->timestamps();

            $table->unique(['document_id', 'employee_id'], 'hr_doc_employee_unique');
        });

        // 4. Expediente / Legajo Digital
        Schema::create('hr_employee_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('hr_employees')->cascadeOnDelete();
            $table->enum('category', [
                'datos_personales',
                'datos_laborales',
                'curriculum',
                'dni_documentos',
                'contratos',
                'apto_medico',
                'declaraciones_juradas',
                'capacitaciones',
                'certificados',
                'recibos_sueldo',
                'sanciones_comunicaciones',
                'egreso',
                'otros'
            ])->default('otros');
            $table->string('title', 180);
            $table->string('file_path', 255);
            $table->string('file_name', 200);
            $table->unsignedBigInteger('file_size')->default(0);
            $table->string('file_hash', 64)->nullable(); // SHA-256
            $table->string('file_mime', 100)->nullable();
            $table->date('issue_date')->nullable();
            $table->date('expiration_date')->nullable();
            $table->enum('visibility_level', ['privado_rrhh', 'responsable', 'empleado', 'publico'])->default('empleado');
            $table->integer('version')->default(1);
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down()
    {
        Schema::dropIfExists('hr_employee_files');
        Schema::dropIfExists('hr_document_assignments');
        Schema::dropIfExists('hr_documents');
        Schema::dropIfExists('hr_document_types');
    }
}
