<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateHrPhase3Tables extends Migration
{
    public function up()
    {
        // 1. Feriados Internos y Nacionales (administrables por RR. HH.)
        if (!Schema::hasTable('hr_holidays')) {
            Schema::create('hr_holidays', function (Blueprint $table) {
                $table->id();
                $table->string('name', 150);
                $table->date('date');
                $table->integer('year')->nullable();
                $table->string('type', 50)->default('national'); // national, provincial, company, non_working
                $table->boolean('is_recurring')->default(false); // Solo true para feriados de fecha fija
                $table->foreignId('branch_id')->nullable()->constrained('hr_branches')->nullOnDelete();
                $table->boolean('is_active')->default(true);
                $table->text('description')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->unique(['date', 'branch_id']);
            });
        } else {
            Schema::table('hr_holidays', function (Blueprint $table) {
                if (!Schema::hasColumn('hr_holidays', 'branch_id')) {
                    $table->foreignId('branch_id')->nullable()->constrained('hr_branches')->nullOnDelete()->after('is_recurring');
                }
                if (!Schema::hasColumn('hr_holidays', 'type')) {
                    $table->string('type', 50)->default('national')->after('year');
                }
            });
        }

        // 2. Políticas de Licencias y Escalas de Vacaciones
        if (!Schema::hasTable('hr_leave_policies')) {
            Schema::create('hr_leave_policies', function (Blueprint $table) {
                $table->id();
                $table->foreignId('leave_type_id')->constrained('hr_leave_types')->cascadeOnDelete();
                $table->foreignId('agreement_id')->nullable()->constrained('hr_agreements')->nullOnDelete();
                $table->string('name', 150)->nullable();
                $table->integer('seniority_years_from')->default(0);
                $table->integer('seniority_years_to')->nullable(); // null = en adelante
                $table->decimal('days_granted', 6, 1)->default(0);
                $table->boolean('allow_transfer')->default(true);
                $table->decimal('max_transferred_days', 6, 1)->nullable();
                $table->integer('transfer_expiration_months')->default(6);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        // 3. Log de pasos del flujo de aprobación de licencias
        if (!Schema::hasTable('hr_leave_approval_logs')) {
            Schema::create('hr_leave_approval_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('leave_request_id')->constrained('hr_leave_requests')->cascadeOnDelete();
                $table->foreignId('approver_id')->nullable()->constrained('users')->nullOnDelete();
                $table->enum('level', ['empleado', 'manager', 'rrhh', 'sistema'])->default('manager');
                $table->string('action', 50); // solicitada, aprobada_manager, aprobada_rrhh, rechazada, cancelada
                $table->text('comment')->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->timestamps();
            });
        }

        // 4. Asignaciones de Solicitudes Multi-Período / Multianuales
        if (!Schema::hasTable('hr_leave_request_allocations')) {
            Schema::create('hr_leave_request_allocations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('leave_request_id')->constrained('hr_leave_requests')->cascadeOnDelete();
                $table->foreignId('leave_balance_id')->constrained('hr_leave_balances')->cascadeOnDelete();
                $table->integer('period_year');
                $table->decimal('days_allocated', 6, 1)->default(0);
                $table->string('status', 30)->default('pending'); // pending, used, released
                $table->timestamps();
            });
        }

        // 5. Ajustes en hr_leave_types
        Schema::table('hr_leave_types', function (Blueprint $table) {
            if (!Schema::hasColumn('hr_leave_types', 'allows_negative_balance')) {
                $table->boolean('allows_negative_balance')->default(false)->after('requires_approval');
            }
            if (!Schema::hasColumn('hr_leave_types', 'requires_reason')) {
                $table->boolean('requires_reason')->default(true)->after('allows_negative_balance');
            }
            if (!Schema::hasColumn('hr_leave_types', 'display_order')) {
                $table->integer('display_order')->default(0)->after('requires_reason');
            }
            if (!Schema::hasColumn('hr_leave_types', 'max_days_limit')) {
                $table->decimal('max_days_limit', 5, 1)->nullable()->after('days_allowed_per_year');
            }
        });

        // 6. Campos adicionales en hr_leave_balances para snapshots, consolidación de ajustes y cierre de período
        Schema::table('hr_leave_balances', function (Blueprint $table) {
            if (!Schema::hasColumn('hr_leave_balances', 'adjustment_days')) {
                $table->decimal('adjustment_days', 6, 1)->default(0)->after('transferred_days');
            }
            if (!Schema::hasColumn('hr_leave_balances', 'leave_policy_id')) {
                $table->foreignId('leave_policy_id')->nullable()->constrained('hr_leave_policies')->nullOnDelete()->after('period_year');
            }
            if (!Schema::hasColumn('hr_leave_balances', 'policy_snapshot')) {
                $table->json('policy_snapshot')->nullable()->after('leave_policy_id');
            }
            if (!Schema::hasColumn('hr_leave_balances', 'is_closed')) {
                $table->boolean('is_closed')->default(false)->after('expiration_date');
            }
            if (!Schema::hasColumn('hr_leave_balances', 'closed_at')) {
                $table->timestamp('closed_at')->nullable()->after('is_closed');
            }
            if (!Schema::hasColumn('hr_leave_balances', 'closed_by')) {
                $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete()->after('closed_at');
            }
            if (!Schema::hasColumn('hr_leave_balances', 'closure_reason')) {
                $table->text('closure_reason')->nullable()->after('closed_by');
            }
        });

        // 7. Campos adicionales en hr_leave_requests
        Schema::table('hr_leave_requests', function (Blueprint $table) {
            if (!Schema::hasColumn('hr_leave_requests', 'confidential_notes')) {
                $table->text('confidential_notes')->nullable()->after('reason');
            }
            if (!Schema::hasColumn('hr_leave_requests', 'attachment_original_name')) {
                $table->string('attachment_original_name', 255)->nullable()->after('attachment_path');
            }
            if (!Schema::hasColumn('hr_leave_requests', 'idempotency_token')) {
                $table->string('idempotency_token', 64)->nullable()->unique()->after('status');
            }
        });

        // Modificar columna status en hr_leave_requests a string(40) si era enum
        try {
            DB::statement("ALTER TABLE `hr_leave_requests` MODIFY COLUMN `status` VARCHAR(40) NOT NULL DEFAULT 'pendiente_rrhh'");
        } catch (\Throwable $e) {
            // Silently ignore if not supported
        }
    }

    public function down()
    {
        Schema::table('hr_leave_requests', function (Blueprint $table) {
            if (Schema::hasColumn('hr_leave_requests', 'idempotency_token')) {
                $table->dropColumn('idempotency_token');
            }
            if (Schema::hasColumn('hr_leave_requests', 'attachment_original_name')) {
                $table->dropColumn('attachment_original_name');
            }
            if (Schema::hasColumn('hr_leave_requests', 'confidential_notes')) {
                $table->dropColumn('confidential_notes');
            }
        });

        Schema::table('hr_leave_balances', function (Blueprint $table) {
            if (Schema::hasColumn('hr_leave_balances', 'closure_reason')) {
                $table->dropColumn('closure_reason');
            }
            if (Schema::hasColumn('hr_leave_balances', 'closed_by')) {
                $table->dropForeign(['closed_by']);
                $table->dropColumn('closed_by');
            }
            if (Schema::hasColumn('hr_leave_balances', 'closed_at')) {
                $table->dropColumn('closed_at');
            }
            if (Schema::hasColumn('hr_leave_balances', 'is_closed')) {
                $table->dropColumn('is_closed');
            }
            if (Schema::hasColumn('hr_leave_balances', 'policy_snapshot')) {
                $table->dropColumn('policy_snapshot');
            }
            if (Schema::hasColumn('hr_leave_balances', 'leave_policy_id')) {
                $table->dropForeign(['leave_policy_id']);
                $table->dropColumn('leave_policy_id');
            }
            if (Schema::hasColumn('hr_leave_balances', 'adjustment_days')) {
                $table->dropColumn('adjustment_days');
            }
        });

        Schema::table('hr_leave_types', function (Blueprint $table) {
            if (Schema::hasColumn('hr_leave_types', 'max_days_limit')) {
                $table->dropColumn('max_days_limit');
            }
            if (Schema::hasColumn('hr_leave_types', 'display_order')) {
                $table->dropColumn('display_order');
            }
            if (Schema::hasColumn('hr_leave_types', 'requires_reason')) {
                $table->dropColumn('requires_reason');
            }
            if (Schema::hasColumn('hr_leave_types', 'allows_negative_balance')) {
                $table->dropColumn('allows_negative_balance');
            }
        });

        Schema::dropIfExists('hr_leave_request_allocations');
        Schema::dropIfExists('hr_leave_approval_logs');
        Schema::dropIfExists('hr_leave_policies');
        Schema::dropIfExists('hr_holidays');
    }
}
