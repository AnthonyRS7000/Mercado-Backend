<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    public function cliente()
    {
        return $this->hasOne(Cliente::class, 'user_id');
    }

    public function delivery()
    {
        return $this->hasOne(Delivery::class, 'user_id');
    }

    public function proveedor()
    {
        return $this->hasOne(Proveedor::class, 'user_id');
    }

    public function personalSistema()
    {
        return $this->hasOne(PersonalSistema::class, 'user_id');
    }

    public function role()
    {
        return $this->belongsTo(Role::class, 'role_id');
    }
}
