<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Personal_Sistema extends Model
{
    protected $table = 'personal_sistemas';

    use HasFactory;
    protected $fillable = [
        'nombre',
        'dni',
        'celular',
        'user_id',
    ];
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function personalSistema()
    {
        return $this->belongsTo(Personal_Sistema::class, 'personal_sistema_id');
    }

}
