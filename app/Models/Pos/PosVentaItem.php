<?php

namespace App\Models\Pos;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PosVentaItem extends Model
{
    protected $table = 'pos_venta_items';

    protected $fillable = ['venta_id', 'producto_id', 'descripcion', 'cantidad', 'precio', 'descuento', 'impuesto_tasa', 'impuesto', 'total', 'costo'];

    protected function casts(): array
    {
        return ['cantidad' => 'decimal:2', 'precio' => 'decimal:2', 'descuento' => 'decimal:2', 'impuesto_tasa' => 'decimal:2', 'impuesto' => 'decimal:2', 'total' => 'decimal:2', 'costo' => 'decimal:2'];
    }

    public $timestamps = false;
    public function producto(): BelongsTo { return $this->belongsTo(PosProducto::class, 'producto_id'); }
}
