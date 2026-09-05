<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('driver_payment_zone_concepts')) {
            Schema::table('driver_payment_zone_concepts', function (Blueprint $table) {
                if (! Schema::hasColumn('driver_payment_zone_concepts', 'vehicle_type')) {
                    $table->string('vehicle_type', 50)->default('general')->after('driver_payment_concept_id');
                }
            });

            $this->dropMatchingUniqueIndex('driver_payment_zone_concepts', ['traffic_zone_id', 'driver_payment_concept_id']);

            if (! $this->hasIndex('driver_payment_zone_concepts', 'driver_payment_zone_concept_vehicle_unique')) {
                Schema::table('driver_payment_zone_concepts', function (Blueprint $table) {
                    $table->unique(
                        ['traffic_zone_id', 'driver_payment_concept_id', 'vehicle_type'],
                        'driver_payment_zone_concept_vehicle_unique'
                    );
                });
            }

            if (! $this->hasIndex('driver_payment_zone_concepts', 'driver_payment_zone_active_vehicle_idx')) {
                Schema::table('driver_payment_zone_concepts', function (Blueprint $table) {
                    $table->index(
                        ['traffic_zone_id', 'vehicle_type', 'active'],
                        'driver_payment_zone_active_vehicle_idx'
                    );
                });
            }
        }

        if (Schema::hasTable('driver_payment_km_ranges')) {
            Schema::table('driver_payment_km_ranges', function (Blueprint $table) {
                if (! Schema::hasColumn('driver_payment_km_ranges', 'vehicle_type')) {
                    $table->string('vehicle_type', 50)->default('general')->after('traffic_zone_id');
                }
            });
        }

        if (Schema::hasTable('driver_payment_zone_settings')) {
            Schema::table('driver_payment_zone_settings', function (Blueprint $table) {
                $columns = [
                    'km_package_threshold' => fn () => $table->unsignedInteger('km_package_threshold')->nullable()->after('package_rate'),
                    'km_excess_package_amount' => fn () => $table->decimal('km_excess_package_amount', 14, 2)->nullable()->after('km_package_threshold'),
                    'km_remote_zone_plus_large' => fn () => $table->decimal('km_remote_zone_plus_large', 14, 2)->nullable()->after('km_excess_package_amount'),
                    'package_delivered_rate' => fn () => $table->decimal('package_delivered_rate', 14, 4)->nullable()->after('km_remote_zone_plus_large'),
                    'package_absent_rate_multiplier' => fn () => $table->decimal('package_absent_rate_multiplier', 8, 4)->nullable()->after('package_delivered_rate'),
                    'package_fixed_amount' => fn () => $table->decimal('package_fixed_amount', 14, 2)->nullable()->after('package_absent_rate_multiplier'),
                    'package_excess_threshold' => fn () => $table->unsignedInteger('package_excess_threshold')->nullable()->after('package_fixed_amount'),
                    'package_excess_amount' => fn () => $table->decimal('package_excess_amount', 14, 2)->nullable()->after('package_excess_threshold'),
                ];

                foreach ($columns as $column => $definition) {
                    if (! Schema::hasColumn('driver_payment_zone_settings', $column)) {
                        $definition();
                    }
                }
            });
        }

        if (! Schema::hasTable('driver_payment_adjustment_rules')) {
            Schema::create('driver_payment_adjustment_rules', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->decimal('amount', 14, 2);
                $table->smallInteger('sign')->default(-1);
                $table->unsignedBigInteger('traffic_zone_id')->nullable();
                $table->unsignedBigInteger('transportista_id')->nullable();
                $table->string('vehicle_type', 50)->default('general');
                $table->boolean('active')->default(true);
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->foreign('traffic_zone_id')->references('id')->on('traffic_zones')->nullOnDelete();
                $table->foreign('transportista_id')->references('id')->on('transportistas')->nullOnDelete();
                $table->index(['traffic_zone_id', 'transportista_id', 'vehicle_type', 'active'], 'driver_payment_adjustment_rules_scope_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('driver_payment_adjustment_rules');

        if (Schema::hasTable('driver_payment_zone_settings')) {
            Schema::table('driver_payment_zone_settings', function (Blueprint $table) {
                $drop = [];
                foreach ([
                    'km_package_threshold',
                    'km_excess_package_amount',
                    'km_remote_zone_plus_large',
                    'package_delivered_rate',
                    'package_absent_rate_multiplier',
                    'package_fixed_amount',
                    'package_excess_threshold',
                    'package_excess_amount',
                ] as $column) {
                    if (Schema::hasColumn('driver_payment_zone_settings', $column)) {
                        $drop[] = $column;
                    }
                }

                if (! empty($drop)) {
                    $table->dropColumn($drop);
                }
            });
        }

        if (Schema::hasTable('driver_payment_km_ranges') && Schema::hasColumn('driver_payment_km_ranges', 'vehicle_type')) {
            Schema::table('driver_payment_km_ranges', function (Blueprint $table) {
                $table->dropColumn('vehicle_type');
            });
        }

        if (Schema::hasTable('driver_payment_zone_concepts') && Schema::hasColumn('driver_payment_zone_concepts', 'vehicle_type')) {
            Schema::table('driver_payment_zone_concepts', function (Blueprint $table) {
                try {
                    $table->dropUnique('driver_payment_zone_concept_vehicle_unique');
                } catch (\Throwable $e) {
                    // ignore
                }
                try {
                    $table->dropIndex('driver_payment_zone_active_vehicle_idx');
                } catch (\Throwable $e) {
                    // ignore
                }
            });

            Schema::table('driver_payment_zone_concepts', function (Blueprint $table) {
                $table->dropColumn('vehicle_type');
                $table->unique(['traffic_zone_id', 'driver_payment_concept_id'], 'driver_payment_zone_concept_unique');
            });
        }
    }

    private function hasIndex(string $table, string $indexName): bool
    {
        foreach (DB::select('SHOW INDEX FROM `' . $table . '`') as $index) {
            if (($index->Key_name ?? null) === $indexName) {
                return true;
            }
        }

        return false;
    }

    private function dropMatchingUniqueIndex(string $table, array $columns): void
    {
        $grouped = [];

        foreach (DB::select('SHOW INDEX FROM `' . $table . '`') as $index) {
            $keyName = $index->Key_name ?? null;
            if (! $keyName || $keyName === 'PRIMARY') {
                continue;
            }

            $grouped[$keyName]['non_unique'] = (int) ($index->Non_unique ?? 1);
            $grouped[$keyName]['columns'][(int) ($index->Seq_in_index ?? 0)] = $index->Column_name ?? null;
        }

        foreach ($grouped as $keyName => $payload) {
            if (($payload['non_unique'] ?? 1) !== 0) {
                continue;
            }

            ksort($payload['columns']);
            $indexedColumns = array_values(array_filter($payload['columns']));
            if ($indexedColumns !== array_values($columns)) {
                continue;
            }

            DB::statement('ALTER TABLE `' . $table . '` DROP INDEX `' . $keyName . '`');
        }
    }
};
