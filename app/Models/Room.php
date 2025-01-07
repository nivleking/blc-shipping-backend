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
    ];

    // Relasi untuk users yang bergabung ke dalam room
    public function users()
    {
        return $this->belongsToMany(User::class);
    }

    // Relasi untuk admin yang membuat room
    public function admin()
    {
        return $this->belongsTo(User::class, 'admin_id');
    }
}
