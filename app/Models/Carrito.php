<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\productos_carrito;

class Carrito extends Model
{
    use HasFactory;

    protected $fillable = [
        'cliente_id', 'uuid'
    ];

    public function productosCarrito()
    {
        return $this->hasMany(productos_carrito::class);
    }

    public function productos()
    {
        return $this->belongsToMany(Producto::class, 'productos_carritos', 'carrito_id', 'producto_id')
                    ->withPivot('cantidad', 'total', 'fecha_agrego', 'estado');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}