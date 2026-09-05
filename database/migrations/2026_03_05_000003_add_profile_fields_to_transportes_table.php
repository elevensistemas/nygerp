<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transportes', function (Blueprint $table) {
            $table->string('driver_name', 255)->nullable()->after('transportista_id');
            $table->string('owner_name', 255)->nullable()->after('driver_name');
            $table->string('status', 50)->nullable()->after('owner_name');
            $table->string('unit_color', 80)->nullable()->after('type');
            $table->string('version', 120)->nullable()->after('model');
            $table->string('chassis_number', 120)->nullable()->after('version');
            $table->string('fuel_type', 80)->nullable()->after('chassis_number');
            $table->string('registration_card', 120)->nullable()->after('fuel_type');
            $table->string('vtv', 120)->nullable()->after('registration_card');
            $table->string('insurance', 120)->nullable()->after('vtv');
            $table->string('satellite', 255)->nullable()->after('insurance');
            $table->unsignedSmallInteger('doors_count')->nullable()->after('satellite');
            $table->string('tank_capacity', 60)->nullable()->after('doors_count');
            $table->string('fuel_consumption_avg', 80)->nullable()->after('tank_capacity');
        });
    }

    public function down(): void
    {
        Schema::table('transportes', function (Blueprint $table) {
            $table->dropColumn([
                'driver_name',
                'owner_name',
                'status',
                'unit_color',
                'version',
                'chassis_number',
                'fuel_type',
                'registration_card',
                'vtv',
                'insurance',
                'satellite',
                'doors_count',
                'tank_capacity',
                'fuel_consumption_avg',
            ]);
        });
    }
};
