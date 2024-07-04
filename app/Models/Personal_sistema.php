<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Personal_sistema extends Model
{
    use HasFactory;
    protected $fillable = [
        'nombre',
        'dni',
        'celular',
        'user_id',
    ];  
}