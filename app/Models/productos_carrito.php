<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class productos_carrito extends Model
{
    use HasFactory;

    protected $fillable = [
        'cantidad',
        'fecha_agrego',
        'total',
        'estado',
        'cliente_id',
        'producto_id',
        'carrito_id' // Añadir carrito_id a los fillables
    ];

    public function producto()
    {
        return $this->belongsTo(Producto::class);
    }

    public function carrito()
    {
        return $this->belongsTo(Carrito::class);
    }
}
