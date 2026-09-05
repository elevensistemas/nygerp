<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'document_id',
        'date',
        'amount',
        'method',
        'notes',
    ];

    public function document()
    {
        return $this->belongsTo(Document::class);
    }
}
