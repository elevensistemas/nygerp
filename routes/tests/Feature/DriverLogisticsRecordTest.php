<?php

namespace Tests\Feature;

use App\Models\DriverLogisticsRecord;
use App\Models\Transportista;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DriverLogisticsRecordTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_requires_authentication()
    {
        $response = $this->get('/traffic/planilla-choferes');
        $response->assertRedirect('/login');
    }

    public function test_index_displays_records_and_filters()
    {
        $user = User::factory()->create([
            'role' => 'admin',
            'accepted_at' => now(),
        ]);
        $this->actingAs($user);

        $driver1 = Transportista::create(['name' => 'Chofer Uno', 'is_active' => true]);
        $driver2 = Transportista::create(['name' => 'Chofer Dos', 'is_active' => true]);

        $record1 = DriverLogisticsRecord::create([
            'fecha' => '2026-08-01',
            'transportista_id' => $driver1->id,
            'zona' => 'ZONA_A',
        ]);

        $record2 = DriverLogisticsRecord::create([
            'fecha' => '2026-08-02',
            'transportista_id' => $driver2->id,
            'zona' => 'ZONA_B',
        ]);

        // Request without filters
        $response = $this->get('/traffic/planilla-choferes?' . http_build_query([
            'fecha_desde' => '2026-08-01',
            'fecha_hasta' => '2026-08-31',
        ]));
        $response->assertStatus(200);
        $response->assertViewHas('records');
        $this->assertCount(2, $response->viewData('records'));

        // Filter by transportista_id
        $response = $this->get('/traffic/planilla-choferes?' . http_build_query([
            'fecha_desde' => '2026-08-01',
            'fecha_hasta' => '2026-08-31',
            'transportista_id' => $driver1->id,
        ]));
        $response->assertStatus(200);
        $this->assertCount(1, $response->viewData('records'));
        $this->assertEquals($record1->id, $response->viewData('records')[0]->id);

        // Filter by concept (columna zona)
        $response = $this->get('/traffic/planilla-choferes?' . http_build_query([
            'fecha_desde' => '2026-08-01',
            'fecha_hasta' => '2026-08-31',
            'concepto' => 'ZONA_B',
        ]));
        $response->assertStatus(200);
        $this->assertCount(1, $response->viewData('records'));
        $this->assertEquals($record2->id, $response->viewData('records')[0]->id);
    }

    public function test_save_creates_records_and_returns_saved_rows_mapping()
    {
        $user = User::factory()->create([
            'role' => 'admin',
            'accepted_at' => now(),
        ]);
        $this->actingAs($user);

        $driver = Transportista::create(['name' => 'Chofer Uno', 'is_active' => true]);

        $postData = [
            'rows' => [
                [
                    'temp_id' => 'row_123',
                    'fecha' => '2026-08-10',
                    'transportista_id' => $driver->id,
                    'zona' => 'ZONA_A',
                ]
            ],
            'deleted_ids' => [],
        ];

        $response = $this->postJson('/traffic/planilla-choferes/save', $postData);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'message',
            'saved_rows' => [
                '*' => ['temp_id', 'id']
            ]
        ]);

        $savedRows = $response->json('saved_rows');
        $this->assertCount(1, $savedRows);
        $this->assertEquals('row_123', $savedRows[0]['temp_id']);
        $this->assertNotNull($savedRows[0]['id']);

        $this->assertDatabaseHas('driver_logistics_records', [
            'id' => $savedRows[0]['id'],
            'transportista_id' => $driver->id,
            'fecha' => '2026-08-10',
            'zona' => 'ZONA_A',
        ]);
    }

    public function test_save_with_non_existent_record_id_creates_new_record_instead_of_failing()
    {
        $user = User::factory()->create([
            'role' => 'admin',
            'accepted_at' => now(),
        ]);
        $this->actingAs($user);

        $driver = Transportista::create(['name' => 'Chofer Uno', 'is_active' => true]);

        $postData = [
            'rows' => [
                [
                    'id' => 99999, // Non-existent ID in DB
                    'temp_id' => 'row_missing_99999',
                    'fecha' => '2026-08-12',
                    'transportista_id' => $driver->id,
                    'zona' => 'ZONA_C',
                ]
            ],
            'deleted_ids' => [],
        ];

        $response = $this->postJson('/traffic/planilla-choferes/save', $postData);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
        
        $savedRows = $response->json('saved_rows');
        $this->assertCount(1, $savedRows);
        $this->assertNotEquals(99999, $savedRows[0]['id']);

        $this->assertDatabaseHas('driver_logistics_records', [
            'transportista_id' => $driver->id,
            'fecha' => '2026-08-12',
            'zona' => 'ZONA_C',
        ]);
    }

    public function test_save_deduplicates_concurrent_new_row_requests_without_id()
    {
        $user = User::factory()->create([
            'role' => 'admin',
            'accepted_at' => now(),
        ]);
        $this->actingAs($user);

        $driver = Transportista::create(['name' => 'Chofer Deduplicar', 'is_active' => true]);

        // First request sends a new row without ID
        $postData1 = [
            'rows' => [
                [
                    'id' => null,
                    'temp_id' => 'row_temp_1',
                    'fecha' => '2026-09-01',
                    'transportista_id' => $driver->id,
                    'ruta' => 'RUTA_1',
                    'paradas' => 10,
                ]
            ],
            'deleted_ids' => [],
        ];

        $response1 = $this->postJson('/traffic/planilla-choferes/save', $postData1);
        $response1->assertStatus(200);
        $savedId = $response1->json('saved_rows.0.id');
        $this->assertNotNull($savedId);

        // Simulated concurrent request (e.g. autosave race condition) sending the same row before front-end received the DB ID
        $postData2 = [
            'rows' => [
                [
                    'id' => null, // Still null because race condition
                    'temp_id' => 'row_temp_1',
                    'fecha' => '2026-09-01',
                    'transportista_id' => $driver->id,
                    'ruta' => 'RUTA_1',
                    'paradas' => 15, // Updated value
                ]
            ],
            'deleted_ids' => [],
        ];

        $response2 = $this->postJson('/traffic/planilla-choferes/save', $postData2);
        $response2->assertStatus(200);
        $savedId2 = $response2->json('saved_rows.0.id');

        // Should return the SAME record ID instead of creating a second record
        $this->assertEquals($savedId, $savedId2);

        // Verify only 1 record exists in DB for this driver/date/route
        $count = DriverLogisticsRecord::where('transportista_id', $driver->id)
            ->where('fecha', '2026-09-01')
            ->where('ruta', 'RUTA_1')
            ->count();
        $this->assertEquals(1, $count);

        $this->assertDatabaseHas('driver_logistics_records', [
            'id' => $savedId,
            'paradas' => 15,
        ]);
    }

    public function test_clean_duplicates_artisan_command_and_endpoint()
    {
        $user = User::factory()->create([
            'role' => 'admin',
            'accepted_at' => now(),
        ]);
        $this->actingAs($user);

        $driver = Transportista::create(['name' => 'Chofer Repetido', 'is_active' => true]);

        // Create 3 existing duplicate records in DB manually
        $rec1 = DriverLogisticsRecord::create([
            'fecha' => '2026-09-01',
            'transportista_id' => $driver->id,
            'paradas' => 0,
        ]);

        $rec2 = DriverLogisticsRecord::create([
            'fecha' => '2026-09-01',
            'transportista_id' => $driver->id,
            'paradas' => 20, // Record with more details
            'observacion' => 'Viaje completado',
        ]);

        $rec3 = DriverLogisticsRecord::create([
            'fecha' => '2026-09-01',
            'transportista_id' => $driver->id,
            'paradas' => 0,
        ]);

        $this->assertEquals(3, DriverLogisticsRecord::where('transportista_id', $driver->id)->count());

        // Test Endpoint
        $response = $this->postJson('/traffic/planilla-choferes/clean-duplicates');
        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        // Verify duplicates reduced from 3 to 1
        $this->assertEquals(1, DriverLogisticsRecord::where('transportista_id', $driver->id)->count());

        // Verify the record kept is rec2 (which has the highest detail score)
        $this->assertDatabaseHas('driver_logistics_records', [
            'id' => $rec2->id,
            'paradas' => 20,
            'observacion' => 'Viaje completado',
        ]);
    }

    public function test_clean_duplicates_get_url()
    {
        $user = User::factory()->create([
            'role' => 'admin',
            'accepted_at' => now(),
        ]);
        $this->actingAs($user);

        $response = $this->get('/traffic/planilla-choferes/clean-duplicates-run');
        $response->assertStatus(200);
        $response->assertSee('Limpieza de Duplicados Ejecutada Exitosamente');
    }

    public function test_destroy_single_record_endpoint()
    {
        $user = User::factory()->create([
            'role' => 'admin',
            'accepted_at' => now(),
        ]);
        $this->actingAs($user);

        $driver = Transportista::create(['name' => 'Chofer Borrar', 'is_active' => true]);

        $record = DriverLogisticsRecord::create([
            'fecha' => '2026-09-01',
            'transportista_id' => $driver->id,
        ]);

        $response = $this->deleteJson("/traffic/planilla-choferes/{$record->id}");
        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $this->assertDatabaseMissing('driver_logistics_records', [
            'id' => $record->id,
        ]);
    }
}
