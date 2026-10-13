<?php

namespace App\Models\Pos;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PosCompra extends Model
{
    protected $table = 'pos_compras';

    protected $fillable = ['cyber_id', 'proveedor_id', 'user_id', 'numero_factura', 'fecha', 'total', 'notas', 'estado'];

    protected function casts(): array
    {
        return ['fecha' => 'date', 'total' => 'decimal:2'];
    }

    public function items(): HasMany { return $this->hasMany(PosCompraItem::class, 'compra_id'); }
    public function proveedor(): BelongsTo { return $this->belongsTo(PosProveedor::class, 'proveedor_id'); }
    public function usuario(): BelongsTo { return $this->belongsTo(\App\Models\User::class, 'user_id'); }
}
