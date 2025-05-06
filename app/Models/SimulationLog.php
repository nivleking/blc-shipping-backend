<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SimulationLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'room_id',
        'arena_bay',
        'arena_dock',
        'port',
        'section',
        'total_revenue',
        'round',
        'revenue',
        'penalty',
    ];

    protected $casts = [
        'arena_bay' => 'array',
        'arena_dock' => 'array',
    ];
}
