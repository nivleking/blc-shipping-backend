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

    public function admin()
    {
        return $this->belongsTo(Admin::class);
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }
}
