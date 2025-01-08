<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SalesCallCard extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',
        'priority',
        'origin',
        'destination',
        'quantity',
        'revenue',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'revenue' => 'integer',
    ];
}
