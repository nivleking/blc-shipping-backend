<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShipBay extends Model
{
    use HasFactory;

    protected $fillable = [
        'arena',
        'port',
        'user_id',
        'room_id',
        'revenue',
        'section',
        'penalty',
        'extra_moves_penalty',
        'backlog_penalty',
        'restowage_penalty',
        'restowage_containers',
        'backlog_containers',
        'restowage_moves',
        'discharge_moves',
        'load_moves',
        'processed_cards',
        'accepted_cards',
        'rejected_cards',
        'current_round',
        'current_round_cards'
    ];

    protected $casts = [
        'arena' => 'array',
        'backlog_containers' => 'array',
        'restowage_containers' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
