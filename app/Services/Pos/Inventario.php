<?php

namespace App\Services\Pos;

use App\Models\Pos\PosMovimiento;
use App\Models\Pos\PosProducto;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Toda entrada o salida de inventario pasa por aquí y deja su registro (kardex). */
class Inventario
{
    /**
     * @param float $cantidad positiva = entra, negativa = sale
     * @param float|null $costoEntrada en compras, para el costo promedio
     */
    public static function mover(PosProducto $p, float $cantidad, string $tipo, ?string $referencia, ?int $userId, ?float $costoEntrada = null): void
    {
        DB::transaction(function () use ($p, $cantidad, $tipo, $referencia, $userId, $costoEntrada) {
            $p = PosProducto::whereKey($p->id)->lockForUpdate()->firstOrFail();
            if (!$p->controla_stock) {
                return;
            }
            $antes = (float) $p->stock;
            $despues = round($antes + $cantidad, 2);

            if ($despues < 0) {
                throw ValidationException::withMessages(['cantidad' => 'No hay existencias suficientes de «' . $p->nombre . '» para esta operación.']);
            }
            // Costo promedio ponderado al comprar.
            if ($costoEntrada !== null && $cantidad > 0) {
                $p->costo = $antes > 0 ? round(($antes * (float) $p->costo + $cantidad * $costoEntrada) / ($antes + $cantidad), 2) : $costoEntrada;
            }
            $p->stock = $despues;
            $p->save();

            PosMovimiento::create([
                'cyber_id' => $p->cyber_id, 'producto_id' => $p->id, 'user_id' => $userId, 'tipo' => $tipo,
                'cantidad' => $cantidad, 'stock_despues' => $despues, 'costo' => $costoEntrada ?? $p->costo,
                'referencia' => $referencia, 'created_at' => now(),
            ]);
        });
    }
}
