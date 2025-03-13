<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Card extends Model
{
    use HasFactory;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'type',
        'priority',
        'origin',
        'destination',
        'quantity',
        'revenue',
        'is_committed',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'revenue' => 'integer',
        'is_committed' => 'boolean',
    ];

    public function decks()
    {
        return $this->belongsToMany(Deck::class, 'card_deck', 'card_id', 'deck_id');
    }

    public function containers()
    {
        return $this->hasMany(Container::class);
    }
}
