<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShipDock extends Model
{
    use HasFactory;

    protected $fillable = ['arena', 'port', 'user_id', 'room_id', 'dock_size'];

    protected $casts = [
        'arena' => 'array',
        'dock_size' => 'array',
    ];
}
