<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PedidoProgramado extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'metodo_pago_id',
        'direccion_entrega',
        'fecha_programada',
        'hora_programada',
        'estado'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function metodoPago()
    {
        return $this->belongsTo(MetodoPago::class);
    }
}
