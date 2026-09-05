<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('traffic_loose_stops', function (Blueprint $table) {
            $table->string('sender_name', 255)->nullable()->after('address');
            $table->string('sender_address', 500)->nullable()->after('sender_name');
            $table->string('sender_contact', 255)->nullable()->after('sender_address');
            $table->string('recipient_name', 255)->nullable()->after('sender_contact');
            $table->string('recipient_address', 500)->nullable()->after('recipient_name');
            $table->string('recipient_contact', 255)->nullable()->after('recipient_address');
            $table->string('tracking_number', 120)->nullable()->after('recipient_contact');
            $table->decimal('length', 10, 2)->nullable()->after('tracking_number');
            $table->decimal('width', 10, 2)->nullable()->after('length');
            $table->decimal('height', 10, 2)->nullable()->after('width');
            $table->decimal('weight_actual', 10, 3)->nullable()->after('height');
            $table->decimal('weight_volumetric', 10, 3)->nullable()->after('weight_actual');
            $table->string('content_description', 500)->nullable()->after('weight_volumetric');
            $table->decimal('declared_value', 14, 2)->nullable()->after('content_description');
            $table->string('barcode', 255)->nullable()->after('declared_value');
        });
    }

    public function down(): void
    {
        Schema::table('traffic_loose_stops', function (Blueprint $table) {
            $table->dropColumn([
                'sender_name',
                'sender_address',
                'sender_contact',
                'recipient_name',
                'recipient_address',
                'recipient_contact',
                'tracking_number',
                'length',
                'width',
                'height',
                'weight_actual',
                'weight_volumetric',
                'content_description',
                'declared_value',
                'barcode',
            ]);
        });
    }
};
