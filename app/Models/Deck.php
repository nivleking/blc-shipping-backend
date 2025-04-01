<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Deck extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'description'];

    public function cards()
    {
        return $this->belongsToMany(Card::class, 'card_deck', 'deck_id', 'card_id');
    }

    public function marketIntelligence()
    {
        return $this->hasOne(MarketIntelligence::class);
    }
}
