<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Entrega extends Model
{
    use HasFactory;

    protected $table = 'entregas'; // 👈 asegura que el nombre de la tabla sea plural

    protected $fillable = [
        'fecha_entrega',
        'imagen_entregas',
        'comentario',
        'estado',
        'precio',
        'pedido_id',
        'delivery_id',
    ];

    public function pedido()
    {
        return $this->belongsTo(Pedido::class, 'pedido_id');
    }

    public function delivery()
    {
        return $this->belongsTo(Delivery::class, 'delivery_id');
    }
}
