<?php
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Http\Kernel::class);
$response = $kernel->handle(\Illuminate\Http\Request::capture());

$settings = \Illuminate\Support\Facades\DB::table('driver_payment_concepts')->get()->toArray();
echo json_encode($settings, JSON_PRETTY_PRINT);
