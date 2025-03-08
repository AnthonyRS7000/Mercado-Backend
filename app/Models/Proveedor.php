<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Proveedor extends Model
{
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
        return $this->hasMany(Pedido::class, 'proveedor_id');
    }

}
