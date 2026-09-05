<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('driver_liquidation_settings')) {
            Schema::create('driver_liquidation_settings', function (Blueprint $table) {
                $table->id();
                $table->enum('tipo_periodo', ['quincenal', 'mensual']);
                $table->unsignedSmallInteger('anio')->nullable();
                $table->unsignedTinyInteger('mes')->nullable();
                $table->unsignedTinyInteger('quincena')->nullable();
                $table->date('desde');
                $table->date('hasta');
                $table->boolean('activo')->default(true);
                $table->timestamps();
                $table->index(['tipo_periodo', 'anio', 'mes', 'quincena', 'activo'], 'driver_liquidation_settings_lookup_idx');
            });
        }

        if (!Schema::hasTable('driver_import_settings')) {
            Schema::create('driver_import_settings', function (Blueprint $table) {
                $table->id();
                $table->string('setting_key')->unique();
                $table->json('setting_value')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('driver_import_runs')) {
            Schema::create('driver_import_runs', function (Blueprint $table) {
                $table->id();
                $table->string('source_file')->nullable();
                $table->enum('tipo_periodo', ['quincenal', 'mensual']);
                $table->unsignedInteger('rows_processed')->default(0);
                $table->unsignedInteger('rows_created')->default(0);
                $table->unsignedInteger('rows_updated')->default(0);
                $table->unsignedInteger('rows_with_errors')->default(0);
                $table->unsignedBigInteger('created_by')->nullable();
                $table->json('summary')->nullable();
                $table->timestamps();
                $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            });
        }

        if (!Schema::hasTable('driver_import_run_rows')) {
            Schema::create('driver_import_run_rows', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('driver_import_run_id');
                $table->string('sheet_name')->nullable();
                $table->unsignedInteger('row_number')->nullable();
                $table->enum('status', ['created', 'updated', 'skipped', 'error']);
                $table->string('message')->nullable();
                $table->json('payload')->nullable();
                $table->timestamps();
                $table->foreign('driver_import_run_id')->references('id')->on('driver_import_runs')->cascadeOnDelete();
                $table->index(['driver_import_run_id', 'status']);
            });
        }

        if (!Schema::hasTable('recibos_chofer')) {
            Schema::create('recibos_chofer', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('transportista_id');
                $table->enum('tipo_periodo', ['quincenal', 'mensual']);
                $table->date('periodo_desde');
                $table->date('periodo_hasta');
                $table->date('fecha_emision')->nullable();
                $table->string('plaza')->nullable();
                $table->enum('estado', ['cargado', 'en_planilla', 'pagado', 'pendiente_pago', 'anulado'])->default('cargado');
                $table->decimal('importe_total', 14, 2)->default(0);
                $table->unsignedBigInteger('factura_id')->nullable();
                $table->string('factura_ref')->nullable();
                $table->string('origen')->default('excel_trafico');
                $table->string('source_file')->nullable();
                $table->string('source_sheet')->nullable();
                $table->string('source_key')->nullable();
                $table->text('observaciones')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->foreign('transportista_id')->references('id')->on('transportistas');
                $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
                $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();
                $table->index('source_key');
                $table->index(['transportista_id', 'tipo_periodo', 'periodo_desde', 'periodo_hasta'], 'recibo_periodo_lookup_idx');
            });
        }

        if (!Schema::hasTable('recibo_chofer_items')) {
            Schema::create('recibo_chofer_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('recibo_chofer_id');
                $table->string('concepto');
                $table->decimal('cantidad', 14, 3)->nullable();
                $table->decimal('importe_unitario', 14, 2)->nullable();
                $table->decimal('importe', 14, 2)->default(0);
                $table->string('source_key')->nullable();
                $table->json('meta')->nullable();
                $table->timestamps();

                $table->foreign('recibo_chofer_id')->references('id')->on('recibos_chofer')->cascadeOnDelete();
                $table->unique('source_key');
                $table->index('recibo_chofer_id');
            });
        }

        if (!Schema::hasTable('planillas_pago_chofer')) {
            Schema::create('planillas_pago_chofer', function (Blueprint $table) {
                $table->id();
                $table->string('numero')->unique();
                $table->date('fecha');
                $table->enum('estado', ['borrador', 'confirmada', 'pagada_parcial', 'cerrada'])->default('borrador');
                $table->decimal('total', 14, 2)->default(0);
                $table->text('observaciones')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
                $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();
            });
        }

        if (!Schema::hasTable('planilla_pago_chofer_recibos')) {
            Schema::create('planilla_pago_chofer_recibos', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('planilla_pago_chofer_id');
                $table->unsignedBigInteger('recibo_chofer_id');
                $table->decimal('monto_en_planilla', 14, 2);
                $table->enum('estado_pago', ['pagado', 'pendiente', 'sin_resultado'])->default('sin_resultado');
                $table->timestamps();

                $table->foreign('planilla_pago_chofer_id')->references('id')->on('planillas_pago_chofer')->cascadeOnDelete();
                $table->foreign('recibo_chofer_id')->references('id')->on('recibos_chofer')->cascadeOnDelete();
                $table->unique(['planilla_pago_chofer_id', 'recibo_chofer_id'], 'planilla_recibo_unique');
                $table->unique('recibo_chofer_id', 'planilla_recibo_single_assignment_unique');
            });
        }

        if (!Schema::hasTable('transportista_liquidation_meta')) {
            Schema::create('transportista_liquidation_meta', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('transportista_id');
                $table->string('titular')->nullable();
                $table->string('patente')->nullable();
                $table->string('modelo')->nullable();
                $table->string('unidad')->nullable();
                $table->string('plaza')->nullable();
                $table->json('extra')->nullable();
                $table->timestamps();

                $table->foreign('transportista_id')->references('id')->on('transportistas')->cascadeOnDelete();
                $table->unique('transportista_id');
                $table->index(['patente', 'titular']);
            });
        }
    }

    public function down(): void
    {
        // Reparacion idempotente: no se revierte automaticamente.
    }
};
