<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('confirmation_token', 64)->nullable()->after('remember_token');
            $table->timestamp('confirmation_sent_at')->nullable()->after('confirmation_token');
            $table->timestamp('accepted_at')->nullable()->after('confirmation_sent_at');
            $table->string('accepted_ip')->nullable()->after('accepted_at');
            $table->string('accepted_user_agent')->nullable()->after('accepted_ip');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'confirmation_token',
                'confirmation_sent_at',
                'accepted_at',
                'accepted_ip',
                'accepted_user_agent',
            ]);
        });
    }
};
