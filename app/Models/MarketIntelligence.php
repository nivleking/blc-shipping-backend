<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MarketIntelligence extends Model
{
    use HasFactory;

    protected $fillable = [
        'deck_id',
        'name',
        'price_data',
    ];

    protected $casts = [
        'price_data' => 'array',
    ];

    public function deck()
    {
        return $this->belongsTo(Deck::class);
    }
}
