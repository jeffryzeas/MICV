<?php

namespace App\Models\Pos;

use Illuminate\Database\Eloquent\Model;

class PosPendiente extends Model
{
    protected $table = 'pos_pendientes';
    protected $keyType = 'string';
    public $incrementing = false;
    protected $fillable = ['id', 'cyber_id', 'user_id', 'tipo', 'contenido'];

    protected function casts(): array
    {
        return ['contenido' => 'encrypted:array'];
    }
}
