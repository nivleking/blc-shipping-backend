<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShipLayout extends Model
{
    use HasFactory;

    protected $fillable = ['bay_size', 'bay_count'];
}
