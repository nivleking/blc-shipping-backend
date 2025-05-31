<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Room extends Model
{
    use HasFactory;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'admin_id',
        'ship_layout_id',
        'name',
        'description',
        'users',
        'assigned_users',
        'status',
        'deck_id',
        'max_users',
        'bay_size',
        'bay_count',
        'bay_types',
        'total_rounds',
        'move_cost',
        'restowage_cost',
        'dock_warehouse_costs',
        'cards_limit_per_round',
        'cards_must_process_per_round',
        'swap_config'
        // 'extra_moves_cost',
        // 'ideal_crane_split',
    ];

    protected $casts = [
        'bay_size' => 'array',
        'bay_types' => 'array',
        'users' => 'array',
        'assigned_users' => 'array',
        'swap_config' => 'array',
        'dock_warehouse_costs' => 'array',
    ];

    public function users()
    {
        return $this->belongsToMany(User::class);
    }

    public function admin()
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    public function deck()
    {
        return $this->belongsTo(Deck::class);
    }

    public function shipLayout()
    {
        return $this->belongsTo(ShipLayout::class);
    }
}
