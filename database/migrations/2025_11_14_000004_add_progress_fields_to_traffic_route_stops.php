<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('traffic_route_stops', function (Blueprint $table) {
            $table->enum('status', ['pending', 'entregado', 'no_se_encuentra', 'recibido_otra_persona', 'rechazado'])
                ->nullable()
                ->after('notes');
            $table->string('recipient_dni')->nullable()->after('status');
            $table->text('status_notes')->nullable()->after('recipient_dni');
            $table->timestamp('attempted_at')->nullable()->after('status_notes');
            $table->timestamp('completed_at')->nullable()->after('attempted_at');
        });
    }

    public function down(): void
    {
        Schema::table('traffic_route_stops', function (Blueprint $table) {
            $table->dropColumn(['status', 'recipient_dni', 'status_notes', 'attempted_at', 'completed_at']);
        });
    }
};
