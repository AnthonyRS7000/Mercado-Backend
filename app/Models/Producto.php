<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Producto extends Model
{
    protected $fillable = [
        'nombre', 'descripcion', 'estado', 'stock', 'precio', 'categoria_id', 'proveedor_id', 'imagen', 'tipo'
    ];

    public function categoria()
    {
        return $this->belongsTo(Categoria::class);
    }

        public function carritos()
    {
        return $this->belongsToMany(Carrito::class, 'productos_carritos', 'producto_id', 'carrito_id')
                    ->withPivot('cantidad', 'total', 'fecha_agrego', 'estado');
    }

    public function proveedor()
    {
        return $this->belongsTo(Proveedor::class, 'proveedor_id');
    }

}
