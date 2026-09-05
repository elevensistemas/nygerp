<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DriverLogisticsRecord extends Model
{
    use HasFactory;

    protected $table = 'driver_logistics_records';

    protected $fillable = [
        'fecha',
        'transportista_id',
        'transporte_id',
        'traffic_zone_id',
        'svc',
        'ruta',
        'numero',
        'zona',
        'paradas',
        'paquetes',
        'entregados',
        'deja_en_svc',
        'paq_no_colectado',
        'nadie_en_domicilio',
        'negocio_cerrado',
        'qr',
        'fuera_de_zona',
        'zona_inaccesible',
        'rechazado',
        'sin_visitar',
        'fraude',
        'paquete_perdido',
        'paquete_danado',
        'paquete_robado',
        'porcentaje',
        'kilometros',
        'kilometros_estimados',
        'zona_lejana',
        'observacion',
        'comentario_perdido',
        'comentario_danado',
        'comentario_robado',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'fecha' => 'date:Y-m-d',
        'paradas' => 'integer',
        'paquetes' => 'integer',
        'entregados' => 'integer',
        'deja_en_svc' => 'integer',
        'paq_no_colectado' => 'integer',
        'nadie_en_domicilio' => 'integer',
        'negocio_cerrado' => 'integer',
        'qr' => 'integer',
        'fuera_de_zona' => 'integer',
        'zona_inaccesible' => 'integer',
        'rechazado' => 'integer',
        'sin_visitar' => 'integer',
        'fraude' => 'integer',
        'paquete_perdido' => 'integer',
        'paquete_danado' => 'integer',
        'paquete_robado' => 'integer',
        'porcentaje' => 'decimal:2',
        'kilometros' => 'decimal:2',
        'kilometros_estimados' => 'decimal:2',
        'zona_lejana' => 'boolean',
    ];

    public function transportista(): BelongsTo
    {
        return $this->belongsTo(Transportista::class, 'transportista_id');
    }

    public function transporte(): BelongsTo
    {
        return $this->belongsTo(Transporte::class, 'transporte_id');
    }

    public function trafficZone(): BelongsTo
    {
        return $this->belongsTo(TrafficZone::class, 'traffic_zone_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
