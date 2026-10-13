<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClienteHabilidad extends Model
{
    protected $table = 'cliente_habilidades';

    protected $fillable = ['cliente_id', 'habilidad', 'orden'];

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }
}
