<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('transportista_payment_methods')) {
            Schema::create('transportista_payment_methods', function (Blueprint $table) {
                $table->id();
                $table->foreignId('transportista_id')->constrained('transportistas')->cascadeOnDelete();
                $table->foreignId('bank_id')->nullable()->constrained('banks')->nullOnDelete();
                $table->string('cbu', 120);
                $table->string('account_number', 120)->nullable();
                $table->string('description', 255)->nullable();
                $table->boolean('is_default')->default(false);
                $table->timestamps();
            });
        }

        if (Schema::hasTable('transportistas')) {
            $rows = DB::table('transportistas')
                ->where(function ($query) {
                    $query->whereNotNull('cbu')
                        ->orWhereNotNull('account_number')
                        ->orWhereNotNull('bank_id');
                })
                ->get(['id', 'bank_id', 'cbu', 'account_number']);

            foreach ($rows as $row) {
                $cbu = trim((string) ($row->cbu ?? ''));
                $accountNumber = trim((string) ($row->account_number ?? ''));
                if ($cbu === '' && $accountNumber === '' && empty($row->bank_id)) {
                    continue;
                }

                $exists = DB::table('transportista_payment_methods')
                    ->where('transportista_id', $row->id)
                    ->where('cbu', $cbu)
                    ->exists();

                if ($exists) {
                    continue;
                }

                DB::table('transportista_payment_methods')->insert([
                    'transportista_id' => $row->id,
                    'bank_id' => $row->bank_id,
                    'cbu' => $cbu !== '' ? $cbu : ($accountNumber !== '' ? $accountNumber : 'SIN_CBU'),
                    'account_number' => $accountNumber !== '' ? $accountNumber : null,
                    'description' => 'Migrado',
                    'is_default' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('transportista_payment_methods');
    }
};
