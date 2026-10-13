<?php

namespace App\Models\Pos;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PosMovimiento extends Model
{
    protected $table = 'pos_movimientos';

    protected $fillable = ['cyber_id', 'producto_id', 'user_id', 'tipo', 'cantidad', 'stock_despues', 'costo', 'referencia', 'created_at'];

    protected function casts(): array
    {
        return ['cantidad' => 'decimal:2', 'stock_despues' => 'decimal:2', 'costo' => 'decimal:2', 'created_at' => 'datetime'];
    }

    public $timestamps = false;
    public const TIPOS = ['inicial' => 'Inventario inicial', 'compra' => 'Compra', 'venta' => 'Venta', 'ajuste' => 'Ajuste', 'anula_venta' => 'Venta anulada', 'anula_compra' => 'Compra anulada'];
    public function producto(): BelongsTo { return $this->belongsTo(PosProducto::class, 'producto_id'); }
    public function usuario(): BelongsTo { return $this->belongsTo(\App\Models\User::class, 'user_id'); }
}
