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
        'name',
        'description',
        'users',
        'status',
        'deck_id',
        'max_users',
        'bay_size',
        'bay_count',
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
        return $this->belongsTo(SalesCallCardDeck::class);
    }
}
