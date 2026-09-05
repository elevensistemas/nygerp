<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('traffic_route_stop_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('traffic_route_stop_id')->constrained('traffic_route_stops')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status');
            $table->foreignId('delivery_reason_id')->nullable()->constrained('delivery_reasons')->nullOnDelete();
            $table->string('recipient_name')->nullable();
            $table->string('recipient_dni')->nullable();
            $table->boolean('recipient_is_owner')->nullable();
            $table->text('status_notes')->nullable();
            $table->timestamp('happened_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('traffic_route_stop_events');
    }
};
