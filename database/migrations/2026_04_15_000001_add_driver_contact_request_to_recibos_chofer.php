<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('recibos_chofer')) {
            return;
        }

        Schema::table('recibos_chofer', function (Blueprint $table) {
            if (! Schema::hasColumn('recibos_chofer', 'driver_contact_request_comment')) {
                $table->text('driver_contact_request_comment')->nullable()->after('observaciones');
            }

            if (! Schema::hasColumn('recibos_chofer', 'driver_contact_request_date')) {
                $table->timestamp('driver_contact_request_date')->nullable()->after('driver_contact_request_comment');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('recibos_chofer')) {
            return;
        }

        Schema::table('recibos_chofer', function (Blueprint $table) {
            $drops = [];

            if (Schema::hasColumn('recibos_chofer', 'driver_contact_request_comment')) {
                $drops[] = 'driver_contact_request_comment';
            }

            if (Schema::hasColumn('recibos_chofer', 'driver_contact_request_date')) {
                $drops[] = 'driver_contact_request_date';
            }

            if (! empty($drops)) {
                $table->dropColumn($drops);
            }
        });
    }
};
