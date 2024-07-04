<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class metodo_pago extends Model
{
    protected $fillable = [
        'nombre', 'descripcion', 'estado'
    ];

    public function pedidos()
    {
        return $this->hasMany(Pedido::class, 'metodo_pago_id');
    }
}

