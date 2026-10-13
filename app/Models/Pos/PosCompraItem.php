<?php

namespace App\Models\Pos;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PosCompraItem extends Model
{
    protected $table = 'pos_compra_items';

    protected $fillable = ['compra_id', 'producto_id', 'descripcion', 'cantidad', 'costo', 'subtotal'];

    protected function casts(): array
    {
        return ['cantidad' => 'decimal:2', 'costo' => 'decimal:2', 'subtotal' => 'decimal:2'];
    }

    public $timestamps = false;
    public function producto(): BelongsTo { return $this->belongsTo(PosProducto::class, 'producto_id'); }
}
