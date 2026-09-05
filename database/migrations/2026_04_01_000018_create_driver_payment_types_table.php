<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('driver_payment_types')) {
            Schema::create('driver_payment_types', function (Blueprint $table) {
                $table->id();
                $table->string('description')->unique();
                $table->boolean('type');
                $table->timestamps();
            });
        }

        $paidId = $this->firstOrCreateType('Pagado', true);
        $unpaidId = $this->firstOrCreateType('No pagado', false);

        if (Schema::hasTable('planilla_pago_chofer_recibos') && ! Schema::hasColumn('planilla_pago_chofer_recibos', 'driver_payment_type_id')) {
            Schema::table('planilla_pago_chofer_recibos', function (Blueprint $table) {
                $table->unsignedBigInteger('driver_payment_type_id')->nullable()->after('estado_pago');
                $table->foreign('driver_payment_type_id')->references('id')->on('driver_payment_types')->nullOnDelete();
                $table->index('driver_payment_type_id');
            });
        }

        if (Schema::hasTable('planilla_pago_chofer_recibos') && Schema::hasColumn('planilla_pago_chofer_recibos', 'driver_payment_type_id')) {
            DB::table('planilla_pago_chofer_recibos')
                ->whereNull('driver_payment_type_id')
                ->where('estado_pago', 'pagado')
                ->update(['driver_payment_type_id' => $paidId]);

            DB::table('planilla_pago_chofer_recibos')
                ->whereNull('driver_payment_type_id')
                ->where('estado_pago', 'pendiente')
                ->update(['driver_payment_type_id' => $unpaidId]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('planilla_pago_chofer_recibos') && Schema::hasColumn('planilla_pago_chofer_recibos', 'driver_payment_type_id')) {
            Schema::table('planilla_pago_chofer_recibos', function (Blueprint $table) {
                try {
                    $table->dropForeign(['driver_payment_type_id']);
                } catch (\Throwable $e) {
                    // ignore
                }
                try {
                    $table->dropIndex(['driver_payment_type_id']);
                } catch (\Throwable $e) {
                    // ignore
                }
                $table->dropColumn('driver_payment_type_id');
            });
        }

        Schema::dropIfExists('driver_payment_types');
    }

    private function firstOrCreateType(string $description, bool $type): int
    {
        $existing = DB::table('driver_payment_types')->where('description', $description)->first();
        if ($existing) {
            return (int) $existing->id;
        }

        return (int) DB::table('driver_payment_types')->insertGetId([
            'description' => $description,
            'type' => $type ? 1 : 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
};
