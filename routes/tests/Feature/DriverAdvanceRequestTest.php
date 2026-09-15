<?php

namespace Tests\Feature;

use App\Models\DriverAdvanceRequest;
use App\Models\Transportista;
use App\Models\ReciboChofer;
use App\Models\ReciboChoferItem;
use App\Models\User;
use App\Services\DriverPayments\ReceiptRecalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DriverAdvanceRequestTest extends TestCase
{
    use RefreshDatabase;

    private function createDriverUser(string $name = 'Chofer Test', string $email = 'chofer@example.com'): array
    {
        $user = User::factory()->create([
            'role' => User::ROLE_TRANSPORTISTA,
            'email' => $email,
            'name' => $name,
            'accepted_at' => now(),
        ]);

        $transportista = Transportista::create([
            'user_id' => $user->id,
            'name' => $name,
            'email' => $email,
            'is_active' => true,
        ]);

        return [$user, $transportista];
    }

    private function createAdminUser(): User
    {
        return User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'accepted_at' => now(),
        ]);
    }

    public function test_driver_can_request_advance()
    {
        [$user, $transportista] = $this->createDriverUser();
        $this->actingAs($user);

        $response = $this->post('/portal-choferes/adelantos', [
            'monto_pedido' => 15000.50,
            'comentario_chofer' => 'Necesito el adelanto para combustible',
        ]);

        $response->assertRedirect('/portal-choferes/adelantos');
        $response->assertSessionHas('ok');

        $this->assertDatabaseHas('driver_advance_requests', [
            'transportista_id' => $transportista->id,
            'monto_pedido' => 15000.50,
            'comentario_chofer' => 'Necesito el adelanto para combustible',
            'estado' => DriverAdvanceRequest::ESTADO_PENDIENTE,
        ]);
    }

    public function test_driver_cannot_request_multiple_advances_per_month()
    {
        [$user, $transportista] = $this->createDriverUser();
        $this->actingAs($user);

        // First request
        $this->post('/portal-choferes/adelantos', [
            'monto_pedido' => 10000,
        ]);

        // Second request in same month
        $response = $this->post('/portal-choferes/adelantos', [
            'monto_pedido' => 5000,
        ]);

        $response->assertSessionHasErrors();
        $this->assertDatabaseCount('driver_advance_requests', 1);
    }

    public function test_driver_cannot_request_if_suspended()
    {
        [$user, $transportista] = $this->createDriverUser();
        $transportista->update([
            'advance_blocked_until' => now()->addDays(5)->toDateString(),
        ]);

        $this->actingAs($user);

        $response = $this->post('/portal-choferes/adelantos', [
            'monto_pedido' => 10000,
        ]);

        $response->assertSessionHasErrors();
        $this->assertDatabaseCount('driver_advance_requests', 0);
    }

    public function test_admin_can_approve_request()
    {
        [$driverUser, $transportista] = $this->createDriverUser();
        $admin = $this->createAdminUser();

        $request = DriverAdvanceRequest::create([
            'transportista_id' => $transportista->id,
            'monto_pedido' => 20000,
            'fecha_pedido' => now()->toDateString(),
            'estado' => DriverAdvanceRequest::ESTADO_PENDIENTE,
        ]);

        $this->actingAs($admin);

        $response = $this->post("/pago-choferes/adelantos/{$request->id}/resolver", [
            'action' => 'aprobar',
            'observaciones_admin' => 'Aprobado sin problemas',
        ]);

        $response->assertRedirect('/pago-choferes/adelantos');
        $response->assertSessionHas('ok');

        $this->assertDatabaseHas('driver_advance_requests', [
            'id' => $request->id,
            'estado' => DriverAdvanceRequest::ESTADO_APROBADO,
            'monto_aprobado' => 20000,
            'observaciones_admin' => 'Aprobado sin problemas',
            'resolved_by' => $admin->id,
        ]);
    }

    public function test_admin_can_counteroffer_and_driver_can_accept()
    {
        [$driverUser, $transportista] = $this->createDriverUser();
        $admin = $this->createAdminUser();

        $request = DriverAdvanceRequest::create([
            'transportista_id' => $transportista->id,
            'monto_pedido' => 20000,
            'fecha_pedido' => now()->toDateString(),
            'estado' => DriverAdvanceRequest::ESTADO_PENDIENTE,
        ]);

        // 1. Admin counteroffers
        $this->actingAs($admin);
        $response = $this->post("/pago-choferes/adelantos/{$request->id}/resolver", [
            'action' => 'contraofertar',
            'monto_contraoferta' => 12000,
            'observaciones_admin' => 'Solo te puedo adelantar esto',
        ]);

        $response->assertRedirect('/pago-choferes/adelantos');
        $this->assertDatabaseHas('driver_advance_requests', [
            'id' => $request->id,
            'estado' => DriverAdvanceRequest::ESTADO_CONTRAOFERTADO,
            'monto_aprobado' => 12000,
        ]);

        // 2. Driver accepts
        $this->actingAs($driverUser);
        $response = $this->post("/portal-choferes/adelantos/{$request->id}/aceptar-contraoferta");

        $response->assertRedirect('/portal-choferes/adelantos');
        $this->assertDatabaseHas('driver_advance_requests', [
            'id' => $request->id,
            'estado' => DriverAdvanceRequest::ESTADO_APROBADO,
            'monto_aprobado' => 12000,
        ]);
    }

    public function test_recalculator_applies_approved_advances_and_marked_liquidated()
    {
        [$driverUser, $transportista] = $this->createDriverUser();

        // Create approved advance
        $advance = DriverAdvanceRequest::create([
            'transportista_id' => $transportista->id,
            'monto_pedido' => 15000,
            'monto_aprobado' => 15000,
            'fecha_pedido' => now()->toDateString(),
            'estado' => DriverAdvanceRequest::ESTADO_APROBADO,
        ]);

        // Create a settlement receipt
        $receipt = ReciboChofer::create([
            'transportista_id' => $transportista->id,
            'fecha_emision' => now(),
            'tipo_periodo' => 'quincenal',
            'periodo_desde' => '2026-08-01',
            'periodo_hasta' => '2026-08-15',
            'estado' => ReciboChofer::ESTADO_CARGADO,
            'importe_total' => 0,
        ]);

        // Insert a dummy freight item so it recalculates
        $item = ReciboChoferItem::create([
            'recibo_chofer_id' => $receipt->id,
            'concepto' => 'Flete Base',
            'cantidad' => 1,
            'importe_unitario' => 30000,
            'importe' => 30000,
            'source_key' => 'manual-test',
        ]);

        $recalculator = app(ReceiptRecalculator::class);
        $recalculator->recalculate($receipt);

        // Assert advance item is added with negative amount
        $this->assertDatabaseHas('recibo_chofer_items', [
            'recibo_chofer_id' => $receipt->id,
            'concepto' => 'Descuento Adelanto (Solicitud #' . $advance->id . ')',
            'importe' => -15000.00,
            'source_key' => 'advance-request:' . $advance->id,
        ]);

        // Assert advance is linked to this receipt
        $this->assertDatabaseHas('driver_advance_requests', [
            'id' => $advance->id,
            'recibo_chofer_id' => $receipt->id,
        ]);

        // Re-run recalculate should not duplicate the item
        $recalculator->recalculate($receipt);
        $this->assertDatabaseCount('recibo_chofer_items', 2);

        // Delete receipt should revert advance request to null recibo_chofer_id
        $receipt->delete();
        $this->assertDatabaseHas('driver_advance_requests', [
            'id' => $advance->id,
            'recibo_chofer_id' => null,
        ]);
    }
}
