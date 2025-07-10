<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class detalles_pedido extends Model
{
    protected $table = 'detalles_pedidos';
    protected $fillable = [
        'cantidad', 'precio_unitario', 'subtotal', 'pedido_id', 'producto_id','notificado_proveedor','personal_sistema_id'
    ];

    public function pedido()
    {
        return $this->belongsTo(Pedido::class, 'pedido_id');
    }

    public function producto()
    {
        return $this->belongsTo(Producto::class, 'producto_id');
    }

    public function personalSistema()
    {
        return $this->belongsTo(Personal_sistema::class, 'personal_sistema_id');
    }
}
