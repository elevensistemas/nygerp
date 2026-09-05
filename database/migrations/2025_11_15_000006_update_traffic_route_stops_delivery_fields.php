<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('traffic_route_stops', function (Blueprint $table) {
            $table->foreignId('delivery_reason_id')
                ->nullable()
                ->after('status')
                ->constrained('delivery_reasons')
                ->nullOnDelete();
            $table->boolean('recipient_is_owner')
                ->default(true)
                ->after('recipient_dni');
        });

        $reasons = [
            ['name' => 'No hay nadie', 'order_index' => 1],
            ['name' => 'Direccion inorrecta', 'order_index' => 2],
            ['name' => 'Faltan datos', 'order_index' => 3],
            ['name' => 'Rechazado', 'order_index' => 4],
            ['name' => 'Paquete perdido', 'order_index' => 5],
            ['name' => 'Intento robo', 'order_index' => 6],
            ['name' => 'Zona insaccesible', 'order_index' => 7],
        ];

        foreach ($reasons as $reason) {
            DB::table('delivery_reasons')->updateOrInsert(
                ['name' => $reason['name']],
                ['order_index' => $reason['order_index'], 'updated_at' => now(), 'created_at' => now()]
            );
        }

        $reasonByName = DB::table('delivery_reasons')->pluck('id', 'name');

        DB::table('traffic_route_stops')
            ->where('status', 'recibido_otra_persona')
            ->update([
                'status' => 'entregado',
                'recipient_is_owner' => false,
            ]);

        DB::table('traffic_route_stops')
            ->whereIn('status', ['no_se_encuentra', 'no_habia_nadie'])
            ->update([
                'status' => 'no_entregado',
                'delivery_reason_id' => $reasonByName['No hay nadie'] ?? null,
            ]);

        DB::table('traffic_route_stops')
            ->where('status', 'domicilio_inexistente')
            ->update([
                'status' => 'no_entregado',
                'delivery_reason_id' => $reasonByName['Direccion inorrecta'] ?? null,
            ]);

        DB::table('traffic_route_stops')
            ->where('status', 'rechazado')
            ->update([
                'status' => 'no_entregado',
                'delivery_reason_id' => $reasonByName['Rechazado'] ?? null,
            ]);

        DB::statement("ALTER TABLE traffic_route_stops MODIFY status ENUM('pending','entregado','no_entregado') NULL");
    }

    public function down(): void
    {
        DB::table('traffic_route_stops')
            ->where('status', 'no_entregado')
            ->update(['status' => 'rechazado']);

        DB::statement("ALTER TABLE traffic_route_stops MODIFY status ENUM('pending','entregado','no_se_encuentra','recibido_otra_persona','rechazado') NULL");

        Schema::table('traffic_route_stops', function (Blueprint $table) {
            $table->dropConstrainedForeignId('delivery_reason_id');
            $table->dropColumn('recipient_is_owner');
        });
    }
};
