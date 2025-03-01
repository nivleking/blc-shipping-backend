<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CapacityUptake extends Model
{
    use HasFactory;

    protected $fillable = [
        'room_id',
        'user_id',
        'week',
        'capacity_data',
        'sales_calls_data',
    ];

    protected $casts = [
        'capacity_data' => 'array',
        'sales_calls_data' => 'array',
        'week' => 'integer',
    ];

    public function room()
    {
        return $this->belongsTo(Room::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
