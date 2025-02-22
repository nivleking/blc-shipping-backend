<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShipLayout extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'bay_size',
        'bay_count',
        'bay_types',
        'created_by'
    ];

    protected $casts = [
        'bay_size' => 'array',
        'bay_types' => 'array'
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
