<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateHrPortalBulletinEventsTables extends Migration
{
    public function up()
    {
        // 1. Cartelera Digital
        Schema::create('hr_bulletins', function (Blueprint $table) {
            $table->id();
            $table->string('title', 200);
            $table->text('summary')->nullable();
            $table->longText('content');
            $table->enum('category', [
                'noticia',
                'comunicado',
                'novedad',
                'politica',
                'beneficio',
                'urgente'
            ])->default('noticia');
            $table->boolean('is_featured')->default(false);
            $table->boolean('requires_read_confirmation')->default(false);
            $table->string('image_path', 255)->nullable();
            $table->string('attachment_path', 255)->nullable();
            
            // Segmentación
            $table->enum('target_scope', ['empresa', 'area', 'sucursal'])->default('empresa');
            $table->foreignId('department_id')->nullable()->constrained('hr_departments')->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('hr_branches')->nullOnDelete();

            $table->enum('status', ['borrador', 'programado', 'publicado', 'archivado'])->default('publicado');
            $table->dateTime('published_at')->nullable();
            $table->dateTime('expires_at')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        // 2. Confirmación de Lectura de Cartelera
        Schema::create('hr_bulletin_reads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bulletin_id')->constrained('hr_bulletins')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('hr_employees')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->dateTime('read_at');
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->unique(['bulletin_id', 'employee_id'], 'hr_bulletin_employee_read_unique');
        });

        // 3. Calendario de Eventos
        Schema::create('hr_events', function (Blueprint $table) {
            $table->id();
            $table->string('title', 150);
            $table->text('description')->nullable();
            $table->enum('event_type', [
                'cumpleanos',
                'aniversario',
                'feriado',
                'capacitacion',
                'evento_interno',
                'fecha_importante',
                'otro'
            ])->default('evento_interno');
            $table->dateTime('start_date');
            $table->dateTime('end_date')->nullable();
            $table->boolean('is_all_day')->default(true);
            $table->string('color', 20)->default('#ffc107');
            $table->string('location', 150)->nullable();
            
            $table->foreignId('department_id')->nullable()->constrained('hr_departments')->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('hr_branches')->nullOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained('hr_employees')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // 4. Notificaciones Internas de RR. HH.
        Schema::create('hr_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained('hr_employees')->nullOnDelete();
            $table->string('type', 80); // leave_request, leave_approved, document_pending, etc.
            $table->string('title', 180);
            $table->text('message');
            $table->string('action_url', 255)->nullable();
            $table->boolean('is_read')->default(false);
            $table->dateTime('read_at')->nullable();
            $table->timestamps();
        });

        // 5. Auditoría Inmutable de RR. HH.
        Schema::create('hr_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 60); // create, update, delete, approve, reject, adjust_balance, view_sensitive, sign, download
            $table->string('entity_type', 100);
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->text('description')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('hr_audit_logs');
        Schema::dropIfExists('hr_notifications');
        Schema::dropIfExists('hr_events');
        Schema::dropIfExists('hr_bulletin_reads');
        Schema::dropIfExists('hr_bulletins');
    }
}
