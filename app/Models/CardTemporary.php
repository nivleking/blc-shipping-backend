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
        'status',
    ];

    // Add this relationship
    public function card()
    {
        return $this->belongsTo(Card::class);
    }

    // Add user relationship if needed
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Add room relationship if needed
    public function room()
    {
        return $this->belongsTo(Room::class);
    }
}
