<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Cliente extends Model
{
    use HasFactory;
    protected $fillable = [
        'nombre',
        'dni',
        'celular',
        'direccion',
        'preferencias_compra',
        'user_id',
    ];

    public function carritos()
    {
        return $this->hasMany(Carrito::class, 'cliente_id');
    }
}
