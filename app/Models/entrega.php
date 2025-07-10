<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Entrega extends Model
{
    protected $fillable = [
        'fecha_entrega',
        'imagen_entregas',
        'comentario',
        'estado',
        'precio',
        'pedido_id',
        'delivery_id'
    ];

    public function pedido()
    {
        return $this->belongsTo(Pedido::class);
    }

    public function delivery()
    {
        return $this->belongsTo(Delivery::class);
    }
}

