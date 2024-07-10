<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;


class Carrito extends Model
{
    use HasFactory;

    protected $fillable = [
        'cliente_id', 'uuid'
    ];

    public function productosCarrito()
    {
        return $this->hasMany(Productos_Carrito::class);
    }
}
