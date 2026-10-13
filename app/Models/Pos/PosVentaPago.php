<?php

namespace App\Models\Pos;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PosVentaPago extends Model
{
    protected $table = 'pos_venta_pagos';

    protected $fillable = ['venta_id', 'metodo', 'monto', 'referencia'];

    protected function casts(): array
    {
        return ['monto' => 'decimal:2'];
    }

    public $timestamps = false;
}
