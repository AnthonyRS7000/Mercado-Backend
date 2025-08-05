<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SolicitudRegistro extends Model
{
    use HasFactory;

    protected $fillable = [
        'nombre',
        'nombre_empresa',
        'dni',
        'celular',
        'direccion',
        'preferencias_compra',
        'email',
        'password',
        'user_id',
        'tipo',
        'estado',
        'ids',
    ];

    protected $casts = [
        'ids' => 'array',
    ];
}
