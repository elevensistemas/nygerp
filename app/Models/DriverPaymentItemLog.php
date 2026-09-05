<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DriverPaymentItemLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'recibo_chofer_item_id',
        'status',
        'comment',
        'user_id',
    ];

    public function item()
    {
        return $this->belongsTo(ReciboChoferItem::class, 'recibo_chofer_item_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
