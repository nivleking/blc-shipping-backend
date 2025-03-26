<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WeeklyPerformance extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'room_id',
        'week',
        'dry_containers_loaded',
        'reefer_containers_loaded',
        'dry_containers_not_loaded',
        'reefer_containers_not_loaded',
        'committed_dry_containers_not_loaded',
        'committed_reefer_containers_not_loaded',
        'non_committed_dry_containers_not_loaded',
        'non_committed_reefer_containers_not_loaded',
        'revenue',
        'move_costs',
        'extra_moves_penalty',
        'net_result',
        'discharge_moves',
        'load_moves',
        'long_crane_moves',
        'extra_moves_on_long_crane',
        'ideal_crane_split'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function room()
    {
        return $this->belongsTo(Room::class);
    }
}
