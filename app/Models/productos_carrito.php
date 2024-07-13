<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class productos_carrito extends Model
{
    use HasFactory;

    protected $table = 'productos_carritos'; // Asegúrate de que el nombre de la tabla sea correcto

    protected $fillable = [
        'carrito_id', 'producto_id',
        'cantidad', 'total', 
        'fecha_agrego',
        'estado'
    ];

    public function carrito()
    {
        return $this->belongsTo(Carrito::class);
    }

    public function producto()
    {
        return $this->belongsTo(Producto::class);
    }
}
