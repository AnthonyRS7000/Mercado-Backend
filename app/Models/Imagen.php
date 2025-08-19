<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Imagen extends Model
{
    use HasFactory;

    protected $table = 'imagenes';
    protected $primaryKey = 'id_imagen';
    public $timestamps = false; // 👈 porque no usas created_at/updated_at, solo fecha_subida

    protected $fillable = [
        'nombre',
        'url',
        'descripcion',
        'fecha_subida',
    ];
}
