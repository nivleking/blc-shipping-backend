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
}
