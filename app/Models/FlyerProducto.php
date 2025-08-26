<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FlyerProducto extends Model
{
    protected $table = 'flyer_productos';

    protected $fillable = [
        'flyer_id',
        'producto_id',
        'precio_original'
    ];

    public function flyer()
    {
        return $this->belongsTo(Flyer::class);
    }

    public function producto()
    {
        return $this->belongsTo(Producto::class);
    }
}
