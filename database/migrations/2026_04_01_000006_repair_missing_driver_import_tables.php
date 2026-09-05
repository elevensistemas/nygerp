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
                $table->text('message')->nullable();
                $table->json('payload')->nullable();
                $table->timestamps();

                $table->foreign('driver_import_run_id')->references('id')->on('driver_import_runs')->cascadeOnDelete();
                $table->index(['driver_import_run_id', 'status']);
            });
        }
    }

    public function down(): void
    {
        // idempotent repair migration
    }
};
