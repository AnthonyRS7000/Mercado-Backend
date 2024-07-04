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
}
