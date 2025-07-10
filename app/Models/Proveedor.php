<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Proveedor extends Model
{
    protected $table = 'proveedors'; // Nombre correcto de la tabla

    protected $fillable = [
        'nombre', 'nombre_empresa',
        'dni', 'celular', 'direccion', 'user_id'
    ];

    public function categorias()
    {
        return $this->belongsToMany(Categoria::class, 'categoria_proveedor');
    }

    public function pedidos()
    {
        return $this->hasManyThrough(
            Pedido::class,         // Modelo destino
            Detalles_Pedido::class, // Modelo intermedio
            'pedido_id',           // Clave en `detalles_pedidos` que referencia a `pedidos`
            'id',                  // Clave primaria en `pedidos`
            'id',                  // Clave en `productos` que referencia a `proveedors`
            'producto_id'          // Clave en `detalles_pedidos` que referencia a `productos`
        );
    }

    public function productos()
    {
        return $this->hasMany(Producto::class);
    }

}
