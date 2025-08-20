<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PersonalSistema extends Model
{
    use HasFactory;

    // Nombre de la tabla en BD
    protected $table = 'personal_sistemas';

    // Campos que se pueden llenar masivamente
    protected $fillable = [
        'nombre',
        'dni',
        'celular',
        'user_id',
    ];

    // Relación con User
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}
