<?php
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Http\Kernel::class);
$response = $kernel->handle(\Illuminate\Http\Request::capture());

$errors = \App\Models\DriverImportRunRow::where('status', 'error')->latest('id')->take(5)->pluck('message');
echo "Errors:\n" . json_encode($errors, JSON_PRETTY_PRINT) . "\n";

$recibo = App\Models\ReciboChofer::latest('id')->first();
echo "\nLast Recibo ID: " . $recibo->id . "\n";
echo "Items Count: " . App\Models\ReciboChoferItem::where('recibo_chofer_id', $recibo->id)->count() . "\n";
