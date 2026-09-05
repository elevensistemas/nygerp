<?php

namespace Database\Seeders;

use App\Models\DeliveryReason;
use Illuminate\Database\Seeder;

class DeliveryReasonSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            ['name' => 'No hay nadie', 'order_index' => 1, 'color' => '#dc3545'],
            ['name' => 'Direccion inorrecta', 'order_index' => 2, 'color' => '#d63384'],
            ['name' => 'Faltan datos', 'order_index' => 3, 'color' => '#fd7e14'],
            ['name' => 'Rechazado', 'order_index' => 4, 'color' => '#6f42c1'],
            ['name' => 'Paquete perdido', 'order_index' => 5, 'color' => '#0d6efd'],
            ['name' => 'Intento robo', 'order_index' => 6, 'color' => '#20c997'],
            ['name' => 'Zona insaccesible', 'order_index' => 7, 'color' => '#198754'],
        ];

        foreach ($defaults as $item) {
            DeliveryReason::updateOrCreate(
                ['name' => $item['name']],
                ['order_index' => $item['order_index'], 'color' => $item['color']]
            );
        }
    }
}
