<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DriverImportVehicleMap extends Model
{
    protected $fillable = [
        'excel_value',
        'vehicle_type',
    ];
}
