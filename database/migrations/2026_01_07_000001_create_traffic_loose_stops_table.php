<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('traffic_loose_stops', function (Blueprint $table) {
            $table->id();
            $table->foreignId('party_id')->nullable()->constrained('parties')->nullOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('code', 120)->nullable();
            $table->string('address', 500);
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('priority', 30)->nullable();
            $table->text('notes')->nullable();
            $table->string('source_filename', 255)->nullable();
            $table->string('fingerprint', 80)->index();
            $table->foreignId('assigned_route_id')->nullable()->constrained('traffic_routes')->nullOnDelete();
            $table->foreignId('assigned_stop_id')->nullable()->constrained('traffic_route_stops')->nullOnDelete();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('traffic_loose_stops');
    }
};
