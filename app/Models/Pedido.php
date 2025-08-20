<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\Cliente;
use App\Models\User;
use App\Models\DetallesPedido;


class Pedido extends Model
{
    protected $fillable = [
        'fecha', 'estado', 'direccion_entrega', 'total', 'user_id',
        'metodo_pago_id','delivery_id',
        'personal_sistema_id','fecha_programada','hora_programada'
    ];

    public function detalles_pedido()
    {
        return $this->hasMany(DetallesPedido::class, 'pedido_id');
    }


    public function metodo_pago()
    {
        return $this->belongsTo(Metodo_Pago::class, 'metodo_pago_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function cliente()
    {
        return $this->hasOne(Cliente::class, 'user_id', 'user_id');
    }


    public function personalSistema()
    {
        return $this->belongsTo(Personal_Sistema::class, 'personal_sistema_id');
    }

    public function delivery()
    {
        return $this->belongsTo(Delivery::class, 'personal_sistema_id');
    }


    public function proveedores()
    {
        return $this->hasManyThrough(
            Proveedor::class,
            Producto::class,
            'id',             // Clave primaria en `pedidos`
            'id',             // Clave primaria en `proveedores`
            'id',             // Clave primaria en `detalles_pedidos`
            'proveedor_id'    // Clave foránea en `productos`
        )->select('proveedors.id as proveedor_id', 'proveedors.nombre');
    }
}
