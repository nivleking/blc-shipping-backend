<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShipBay extends Model
{
    use HasFactory;

    protected $fillable = ['arena', 'user_id', 'room_id'];

    protected $casts = [
        'arena' => 'array',
    ];
}
