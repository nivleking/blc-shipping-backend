<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Crypt;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'is_admin',
        'is_super_admin',
        'password',
        'password_plain',
        'created_by',
        'updated_by',
        'status',
        'last_login_at',
        'last_login_ip',
        'login_count'
    ];

    protected $hidden = [
        'password',
        'email',
        'password_plain',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'last_login_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'login_count' => 'integer'
    ];

    protected $appends = ['password_plain'];

    // Relasi untuk rooms yang diikuti oleh user
    public function rooms()
    {
        return $this->belongsToMany(Room::class);
    }

    // Relasi untuk rooms yang dibuat oleh user (admin)
    public function createdRooms()
    {
        return $this->hasMany(Room::class, 'admin_id');
    }

    public function setPasswordPlainAttribute($value)
    {
        $this->attributes['password_plain'] = Crypt::encryptString($value);
    }

    public function getPasswordPlainAttribute($value)
    {
        try {
            return $value ? Crypt::decryptString($value) : null;
        } catch (\Exception $e) {
            return null;
        }
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function editor()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
