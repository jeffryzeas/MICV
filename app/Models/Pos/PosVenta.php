<?php

namespace App\Models\Pos;

use App\Models\Cliente;
use App\Models\Cyber;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PosVenta extends Model
{
    protected $table = 'pos_ventas';

    protected $fillable = [
        'cyber_id', 'caja_id', 'user_id', 'cliente_id', 'cliente_nombre', 'consecutivo', 'numero', 'subtotal', 'descuento', 'impuesto',
        'total', 'pagado', 'cambio', 'costo_total', 'estado', 'anulada_por', 'anulada_en', 'motivo_anulacion', 'notas',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2', 'descuento' => 'decimal:2', 'impuesto' => 'decimal:2', 'total' => 'decimal:2', 'pagado' => 'decimal:2',
            'cambio' => 'decimal:2', 'costo_total' => 'decimal:2', 'anulada_en' => 'datetime',
        ];
    }

    public function items(): HasMany { return $this->hasMany(PosVentaItem::class, 'venta_id'); }
    public function pagos(): HasMany { return $this->hasMany(PosVentaPago::class, 'venta_id'); }
    public function caja(): BelongsTo { return $this->belongsTo(PosCaja::class, 'caja_id'); }
    public function cyber(): BelongsTo { return $this->belongsTo(Cyber::class); }
    public function cliente(): BelongsTo { return $this->belongsTo(Cliente::class); }
    public function usuario(): BelongsTo { return $this->belongsTo(User::class, 'user_id'); }
    public function anulo(): BelongsTo { return $this->belongsTo(User::class, 'anulada_por'); }

    public function nombreCliente(): string
    {
        return $this->cliente?->nombreCompleto() ?: ($this->cliente_nombre ?: 'Cliente general');
    }
}
