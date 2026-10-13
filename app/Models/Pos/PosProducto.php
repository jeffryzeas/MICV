<?php

namespace App\Models\Pos;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PosProducto extends Model
{
    protected $table = 'pos_productos';

    protected $fillable = ['cyber_id', 'categoria_id', 'proveedor_id', 'tipo', 'codigo', 'nombre', 'precio', 'costo', 'impuesto_tasa', 'exento', 'controla_stock', 'stock', 'stock_minimo', 'unidad', 'favorito', 'activo'];

    protected function casts(): array
    {
        return ['precio' => 'decimal:2', 'costo' => 'decimal:2', 'impuesto_tasa' => 'decimal:2', 'stock' => 'decimal:2', 'stock_minimo' => 'decimal:2', 'exento' => 'boolean', 'controla_stock' => 'boolean', 'favorito' => 'boolean', 'activo' => 'boolean'];
    }

    public function categoria(): BelongsTo { return $this->belongsTo(PosCategoria::class, 'categoria_id'); }
    public function proveedor(): BelongsTo { return $this->belongsTo(PosProveedor::class, 'proveedor_id'); }
    public function movimientos(): HasMany { return $this->hasMany(PosMovimiento::class, 'producto_id'); }

    /** Tasa de impuesto que aplica a este producto (0 si es exento). */
    public function tasa(PosConfig $cfg): float
    {
        return $this->exento ? 0.0 : (float) ($this->impuesto_tasa ?? $cfg->impuesto_tasa);
    }

    public function stockBajo(): bool
    {
        return $this->controla_stock && (float) $this->stock <= (float) $this->stock_minimo;
    }
}
