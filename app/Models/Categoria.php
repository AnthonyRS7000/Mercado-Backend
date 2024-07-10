<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Categoria extends Model
{
    protected $fillable = ['nombre'];

    public function proveedores()
    {
        return $this->belongsToMany(Proveedor::class, 'categoria_proveedor');
    }
}
