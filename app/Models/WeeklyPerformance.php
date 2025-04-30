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
        'discharge_moves',
        'load_moves',
        'restowage_container_count',
        'restowage_moves',
        'restowage_penalty',
        'unrolled_container_counts',
        'dock_warehouse_container_counts',
        'total_penalty',
        'dock_warehouse_penalty',
        'unrolled_penalty',
        'revenue',
        'move_costs',
        'net_result',
        'dry_containers_loaded',
        'reefer_containers_loaded',
        // 'dry_containers_not_loaded',
        // 'reefer_containers_not_loaded',
        // 'committed_dry_containers_not_loaded',
        // 'committed_reefer_containers_not_loaded',
        // 'non_committed_dry_containers_not_loaded',
        // 'non_committed_reefer_containers_not_loaded',
        // 'extra_moves_penalty',
        // 'long_crane_moves',
        // 'extra_moves_on_long_crane',
        // 'ideal_crane_split'
    ];

    protected $casts = [
        'unrolled_container_counts' => 'array',
        'dock_warehouse_container_counts' => 'array',
        // 'dry_containers_not_loaded' => 'integer',
        // 'reefer_containers_not_loaded' => 'integer',
        // 'committed_dry_containers_not_loaded' => 'integer',
        // 'committed_reefer_containers_not_loaded' => 'integer',
        // 'non_committed_dry_containers_not_loaded' => 'integer',
        // 'non_committed_reefer_containers_not_loaded' => 'integer',
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
