<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class detalles_pedido extends Model
{
    protected $fillable = [
        'cantidad', 'precio_unitario', 'subtotal', 'pedido_id', 'producto_id'
    ];

    public function pedido()
    {
        return $this->belongsTo(Pedido::class, 'pedido_id');
    }

    public function producto()
    {
        return $this->belongsTo(Producto::class, 'producto_id');
    }
}
