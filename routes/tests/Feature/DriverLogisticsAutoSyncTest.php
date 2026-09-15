<?php

namespace Tests\Feature;

use App\Models\DriverLogisticsRecord;
use App\Models\ReciboChofer;
use App\Models\ReciboChoferItem;
use App\Models\Transportista;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DriverLogisticsAutoSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_logistics_record_automatically_creates_driver_receipt_item()
    {
        $driver = Transportista::create([
            'name' => 'Chofer AutoSync Test',
            'is_active' => true,
        ]);

        $record = DriverLogisticsRecord::create([
            'fecha' => '2026-09-05',
            'transportista_id' => $driver->id,
            'zona' => 'ZONA AUTO',
            'paradas' => 10,
            'paquetes' => 20,
            'entregados' => 20,
        ]);

        $item = ReciboChoferItem::whereNotNull('meta')->get()->first(function ($i) use ($record) {
            $meta = is_array($i->meta) ? $i->meta : [];
            return ($meta['logistics_record_id'] ?? null) == $record->id;
        });

        $this->assertNotNull($item, 'ReciboChoferItem debería haberse creado automáticamente para el registro de logística.');
        $this->assertNotNull($item->recibo_chofer_id);

        $recibo = ReciboChofer::find($item->recibo_chofer_id);
        $this->assertNotNull($recibo);
        $this->assertEquals($driver->id, $recibo->transportista_id);
    }

    public function test_save_endpoint_automatically_syncs_driver_receipts()
    {
        $user = User::factory()->create([
            'role' => 'admin',
            'accepted_at' => now(),
        ]);
        $this->actingAs($user);

        $driver = Transportista::create([
            'name' => 'Chofer Batch Test',
            'is_active' => true,
        ]);

        $postData = [
            'rows' => [
                [
                    'temp_id' => 'row_999',
                    'fecha' => '2026-09-06',
                    'transportista_id' => $driver->id,
                    'zona' => 'ZONA BATCH',
                    'paradas' => 5,
                    'paquetes' => 15,
                    'entregados' => 15,
                ]
            ],
            'deleted_ids' => [],
        ];

        $response = $this->postJson('/traffic/planilla-choferes/save', $postData);
        $response->assertStatus(200);

        $record = DriverLogisticsRecord::where('transportista_id', $driver->id)->first();
        $this->assertNotNull($record);

        $item = ReciboChoferItem::whereNotNull('meta')->get()->first(function ($i) use ($record) {
            $meta = is_array($i->meta) ? $i->meta : [];
            return ($meta['logistics_record_id'] ?? null) == $record->id;
        });

        $this->assertNotNull($item);

        // Now test updating the row
        $postDataUpdate = [
            'rows' => [
                [
                    'id' => $record->id,
                    'fecha' => '2026-09-06',
                    'transportista_id' => $driver->id,
                    'zona' => 'ZONA BATCH MODIFICADA',
                    'paradas' => 8,
                    'paquetes' => 25,
                    'entregados' => 25,
                ]
            ],
            'deleted_ids' => [],
        ];

        $responseUpdate = $this->postJson('/traffic/planilla-choferes/save', $postDataUpdate);
        $responseUpdate->assertStatus(200);

        $itemUpdated = ReciboChoferItem::whereNotNull('meta')->get()->first(function ($i) use ($record) {
            $meta = is_array($i->meta) ? $i->meta : [];
            return ($meta['logistics_record_id'] ?? null) == $record->id;
        });

        $this->assertNotNull($itemUpdated);
        $this->assertEquals(25, $itemUpdated->entregados);
    }

    public function test_deleting_logistics_record_removes_driver_receipt_item()
    {
        $user = User::factory()->create([
            'role' => 'admin',
            'accepted_at' => now(),
        ]);
        $this->actingAs($user);

        $driver = Transportista::create([
            'name' => 'Chofer Delete Test',
            'is_active' => true,
        ]);

        $record = DriverLogisticsRecord::create([
            'fecha' => '2026-09-07',
            'transportista_id' => $driver->id,
            'zona' => 'ZONA DELETE',
            'paradas' => 3,
            'paquetes' => 10,
            'entregados' => 10,
        ]);

        $item = ReciboChoferItem::whereNotNull('meta')->get()->first(function ($i) use ($record) {
            $meta = is_array($i->meta) ? $i->meta : [];
            return ($meta['logistics_record_id'] ?? null) == $record->id;
        });
        $this->assertNotNull($item);

        // Delete record via destroy endpoint
        $response = $this->deleteJson("/traffic/planilla-choferes/{$record->id}");
        $response->assertStatus(200);

        $itemDeleted = ReciboChoferItem::whereNotNull('meta')->get()->first(function ($i) use ($record) {
            $meta = is_array($i->meta) ? $i->meta : [];
            return ($meta['logistics_record_id'] ?? null) == $record->id;
        });

        $this->assertNull($itemDeleted, 'El ítem del recibo debería ser eliminado al borrar el registro de logística.');
    }
}
