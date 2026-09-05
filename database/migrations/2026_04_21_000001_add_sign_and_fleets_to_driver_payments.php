<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('driver_payment_concepts') && ! Schema::hasColumn('driver_payment_concepts', 'sign')) {
            Schema::table('driver_payment_concepts', function (Blueprint $table) {
                $table->tinyInteger('sign')->default(1)->after('default_amount');
            });
        }

        if (! Schema::hasTable('driver_payment_fleets')) {
            Schema::create('driver_payment_fleets', function (Blueprint $table) {
                $table->id();
                $table->string('name')->unique();
                $table->unsignedBigInteger('billing_transportista_id')->nullable();
                $table->boolean('active')->default(true);
                $table->timestamps();

                $table->foreign('billing_transportista_id')
                    ->references('id')
                    ->on('transportistas')
                    ->nullOnDelete();
            });
        }

        if (! Schema::hasTable('driver_payment_fleet_transportista')) {
            Schema::create('driver_payment_fleet_transportista', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('driver_payment_fleet_id');
                $table->unsignedBigInteger('transportista_id');
                $table->timestamps();

                $table->foreign('driver_payment_fleet_id')
                    ->name('dpft_fleet_fk')
                    ->references('id')
                    ->on('driver_payment_fleets')
                    ->cascadeOnDelete();
                $table->foreign('transportista_id')
                    ->name('dpft_transportista_fk')
                    ->references('id')
                    ->on('transportistas')
                    ->cascadeOnDelete();
                $table->unique(['driver_payment_fleet_id', 'transportista_id'], 'dpft_member_unique');
                $table->unique('transportista_id', 'dpft_transportista_unique');
            });
        }

        if (Schema::hasTable('recibos_chofer')) {
            Schema::table('recibos_chofer', function (Blueprint $table) {
                if (! Schema::hasColumn('recibos_chofer', 'driver_payment_fleet_id')) {
                    $table->unsignedBigInteger('driver_payment_fleet_id')->nullable()->after('transportista_id');
                    $table->foreign('driver_payment_fleet_id')
                        ->name('recibos_fleet_fk')
                        ->references('id')
                        ->on('driver_payment_fleets')
                        ->nullOnDelete();
                    $table->index(['driver_payment_fleet_id', 'tipo_periodo', 'periodo_desde', 'periodo_hasta'], 'recibo_fleet_period_idx');
                }
            });
        }

        if (Schema::hasTable('recibos_chofer')) {
            DB::table('recibos_chofer')
                ->whereNull('driver_payment_fleet_id')
                ->update(['driver_payment_fleet_id' => null]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('recibos_chofer') && Schema::hasColumn('recibos_chofer', 'driver_payment_fleet_id')) {
            Schema::table('recibos_chofer', function (Blueprint $table) {
                $table->dropForeign('recibos_fleet_fk');
                $table->dropIndex('recibo_fleet_period_idx');
                $table->dropColumn('driver_payment_fleet_id');
            });
        }

        Schema::dropIfExists('driver_payment_fleet_transportista');
        Schema::dropIfExists('driver_payment_fleets');

        if (Schema::hasTable('driver_payment_concepts') && Schema::hasColumn('driver_payment_concepts', 'sign')) {
            Schema::table('driver_payment_concepts', function (Blueprint $table) {
                $table->dropColumn('sign');
            });
        }
    }
};
