<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddCommentsAndLogsToDriverLogisticsRecords extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('driver_logistics_records', function (Blueprint $table) {
            $table->text('comentario_perdido')->nullable()->after('paquete_perdido');
            $table->text('comentario_danado')->nullable()->after('paquete_danado');
            $table->text('comentario_robado')->nullable()->after('paquete_robado');
        });

        Schema::create('driver_logistics_record_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('driver_logistics_record_id')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->string('action');
            $table->text('details')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('driver_logistics_record_logs');

        Schema::table('driver_logistics_records', function (Blueprint $table) {
            $table->dropColumn(['comentario_perdido', 'comentario_danado', 'comentario_robado']);
        });
    }
}
