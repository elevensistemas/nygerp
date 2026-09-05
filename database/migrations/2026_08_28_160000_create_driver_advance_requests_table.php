<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('driver_advance_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transportista_id')->constrained('transportistas')->onDelete('cascade');
            $table->decimal('monto_pedido', 10, 2);
            $table->text('comentario_chofer')->nullable();
            $table->date('fecha_pedido');
            $table->string('estado')->default('pendiente'); // pendiente, aprobado, rechazado, contraofertado
            $table->decimal('monto_aprobado', 10, 2)->nullable();
            $table->datetime('fecha_resolucion')->nullable();
            $table->text('observaciones_admin')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('recibo_chofer_id')->nullable()->constrained('recibos_chofer')->onDelete('set null');
            $table->timestamps();
        });

        Schema::table('transportistas', function (Blueprint $table) {
            $table->date('advance_blocked_until')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('transportistas', function (Blueprint $table) {
            $table->dropColumn('advance_blocked_until');
        });

        Schema::dropIfExists('driver_advance_requests');
    }
};
