<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class productos_carrito extends Model
{
    use HasFactory;

    protected $fillable = 
    ['cantidad','fecha_agrego','total',
    'estado','cliente_id','producto_id'];

    public function productos()
    {
        return $this->belongsToMany(Producto::class)->withPivot('cantidad');
    }
}
