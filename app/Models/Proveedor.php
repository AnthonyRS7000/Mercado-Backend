<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Proveedor extends Model
{
    protected $table = 'proveedors'; // Nombre correcto de la tabla

    protected $fillable = [
        'nombre',
        'nombre_empresa',
        'dni',
        'celular',
        'direccion',
        'user_id'
    ];

    /**
     * Relación con Usuario propietario (para login y validación de contraseña).
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relación muchos a muchos con Categorías.
     */
    public function categorias()
    {
        return $this->belongsToMany(Categoria::class, 'categoria_proveedor');
    }

    /**
     * Relación uno a muchos con Productos.
     */
    public function productos()
    {
        return $this->hasMany(Producto::class);
    }

    /**
     * Relación uno a muchos con Flyers.
     */
    public function flyers()
    {
        return $this->hasMany(Flyer::class);
    }

    /**
     * Relación con pedidos a través de detalles_pedido.
     * ⚠️ Te recomiendo revisar si está bien: depende de cómo están tus claves en la tabla pivot.
     */
    public function pedidos()
    {
        return $this->hasManyThrough(
            Pedido::class,          // Modelo destino
            DetallesPedido::class, // Modelo intermedio
            'producto_id',          // Columna en detalles_pedidos que referencia a productos
            'id',                   // Columna en pedidos (PK)
            'id',                   // Columna en proveedors (PK)
            'pedido_id'             // Columna en detalles_pedidos que referencia a pedidos
        );
    }
}
