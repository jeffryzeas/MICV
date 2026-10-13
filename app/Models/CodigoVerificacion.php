<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CodigoVerificacion extends Model
{
    protected $table = 'codigos_verificacion';

    protected $fillable = ['user_id', 'tipo', 'codigo', 'expira_en', 'intentos', 'usado_en'];

    protected function casts(): array
    {
        return ['expira_en' => 'datetime', 'usado_en' => 'datetime'];
    }
}
