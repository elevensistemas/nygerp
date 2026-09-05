<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use App\Models\TrafficRoute;
$route = TrafficRoute::find(35);
foreach ($route->raw_payload['trip']['legs'] as $index => $leg) {
    var_dump($index, array_key_exists('geometry', $leg));
}
