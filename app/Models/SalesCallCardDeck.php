<?php

namespace App\Models;


use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SalesCallCardDeck extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'description'];

    public function cards()
    {
        return $this->belongsToMany(SalesCallCard::class, 'card_deck', 'deck_id', 'card_id');
    }
}
