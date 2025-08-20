<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Personal_Sistema extends Model
{
    use HasFactory;

    // 🔹 Nombre exacto de la tabla en BD
    protected $table = 'personal_sistemas';

    // 🔹 Campos que se pueden asignar masivamente
    protected $fillable = [
        'nombre',
        'dni',
        'celular',
        'user_id',
    ];

    // 🔹 Relación con User
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    // ⚠️ Esta relación es opcional, quítala si no la usas
    public function personalSistema()
    {
        return $this->belongsTo(Personal_Sistema::class, 'personal_sistema_id');
    }
}
