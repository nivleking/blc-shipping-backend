<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShipBay extends Model
{
    use HasFactory;

    protected $fillable = ['arena', 'port', 'user_id', 'room_id', 'revenue', 'section',];

    protected $casts = [
        'arena' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
