<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CardTemporary extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'room_id',
        'card_id',
        'deck_id',
        'status',
        'round'
    ];

    public function card()
    {
        return $this->belongsTo(Card::class, 'card_id', 'id')
            ->where('deck_id', $this->deck_id);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function room()
    {
        return $this->belongsTo(Room::class);
    }
}
