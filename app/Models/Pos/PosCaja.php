<?php

namespace App\Models\Pos;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Un turno de caja: se abre con un monto inicial y se cierra contando el efectivo. */
class PosCaja extends Model
{
    protected $table = 'pos_cajas';

    protected $fillable = ['cyber_id', 'user_id', 'abierta_en', 'monto_inicial', 'cerrada_en', 'cerrada_por', 'efectivo_esperado', 'efectivo_contado', 'diferencia', 'conteo', 'notas_cierre', 'estado'];

    protected function casts(): array
    {
        return [
            'abierta_en' => 'datetime', 'cerrada_en' => 'datetime', 'monto_inicial' => 'decimal:2', 'efectivo_esperado' => 'decimal:2',
            'efectivo_contado' => 'decimal:2', 'diferencia' => 'decimal:2', 'conteo' => 'array',
        ];
    }

    public function usuario(): BelongsTo { return $this->belongsTo(User::class, 'user_id'); }
    public function cerro(): BelongsTo { return $this->belongsTo(User::class, 'cerrada_por'); }
    public function ventas(): HasMany { return $this->hasMany(PosVenta::class, 'caja_id'); }
    public function movimientos(): HasMany { return $this->hasMany(PosCajaMovimiento::class, 'caja_id'); }

    public static function abiertaDe(int $cyberId): ?self
    {
        return static::where('cyber_id', $cyberId)->where('estado', 'abierta')->latest('id')->first();
    }

    /** Totales del turno (sin ventas anuladas). */
    public function resumen(): array
    {
        $ventas = $this->ventas()->where('estado', 'pagada');
        $ids = (clone $ventas)->pluck('id');
        $porMetodo = PosVentaPago::whereIn('venta_id', $ids)->selectRaw('metodo, SUM(monto) as total, COUNT(*) as n')->groupBy('metodo')->get()
            ->mapWithKeys(fn ($r) => [$r->metodo => (float) $r->total])->all();
        $cambio = (float) (clone $ventas)->sum('cambio');
        $ingresos = (float) $this->movimientos()->where('tipo', 'ingreso')->sum('monto');
        $retiros = (float) $this->movimientos()->where('tipo', 'retiro')->sum('monto');
        $efectivoVentas = ($porMetodo['efectivo'] ?? 0) - $cambio;
        return [
            'ventas' => $ids->count(),
            'total' => (float) (clone $ventas)->sum('total'),
            'impuesto' => (float) (clone $ventas)->sum('impuesto'),
            'anuladas' => $this->ventas()->where('estado', 'anulada')->count(),
            'por_metodo' => array_merge($porMetodo, ['efectivo' => $efectivoVentas]),
            'ingresos' => $ingresos,
            'retiros' => $retiros,
            'esperado' => round((float) $this->monto_inicial + $efectivoVentas + $ingresos - $retiros, 2),
        ];
    }
}
