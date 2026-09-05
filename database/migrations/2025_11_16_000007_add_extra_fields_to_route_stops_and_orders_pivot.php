<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('traffic_route_stops', function (Blueprint $table) {
            $table->boolean('is_extra')->default(false)->after('sequence');
            $table->foreignId('order_id')->nullable()->after('traffic_route_id')->constrained('orders')->nullOnDelete();
        });

        Schema::create('traffic_route_order', function (Blueprint $table) {
            $table->id();
            $table->foreignId('traffic_route_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['traffic_route_id', 'order_id']);
        });

        // Backfill pivot with existing order_id
        $routes = DB::table('traffic_routes')
            ->whereNotNull('order_id')
            ->select('id', 'order_id')
            ->get();

        foreach ($routes as $route) {
            DB::table('traffic_route_order')->updateOrInsert(
                ['traffic_route_id' => $route->id, 'order_id' => $route->order_id],
                ['created_at' => now(), 'updated_at' => now()]
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('traffic_route_order');

        Schema::table('traffic_route_stops', function (Blueprint $table) {
            $table->dropColumn(['is_extra', 'order_id']);
        });
    }
};
