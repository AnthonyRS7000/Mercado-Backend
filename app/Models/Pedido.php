<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;



class Pedido extends Model
{
    protected $fillable = [
        'fecha', 'estado', 'direccion_entrega', 'total', 'user_id', 'metodo_pago_id'
    ];

    public function detalles_pedido()
    {
        return $this->hasMany(detalles_pedido::class, 'pedido_id');
    }

    public function metodo_pago()
    {
        return $this->belongsTo(metodo_pago::class, 'metodo_pago_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }


}
