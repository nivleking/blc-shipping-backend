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
        return $this->hasMany(Card::class);
    }

    public function marketIntelligence()
    {
        return $this->hasOne(MarketIntelligence::class);
    }
}
