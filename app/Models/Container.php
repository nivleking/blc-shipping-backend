<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Container extends Model
{
    use HasFactory;

    protected $fillable = [
        'color',
        'sales_call_card_id',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    public function salesCallCard()
    {
        return $this->belongsTo(SalesCallCard::class);
    }
}
