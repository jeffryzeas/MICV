<?php

namespace App\Models\Pos;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PosCajaMovimiento extends Model
{
    protected $table = 'pos_caja_movimientos';

    protected $fillable = ['caja_id', 'user_id', 'tipo', 'monto', 'motivo'];

    protected function casts(): array
    {
        return ['monto' => 'decimal:2'];
    }

    public function usuario(): BelongsTo { return $this->belongsTo(\App\Models\User::class, 'user_id'); }
}
