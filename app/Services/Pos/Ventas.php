<?php

namespace App\Services\Pos;

use App\Models\Actividad;
use App\Models\Pos\PosCaja;
use App\Models\Pos\PosConfig;
use App\Models\Pos\PosProducto;
use App\Models\Pos\PosVenta;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Cálculo y registro de ventas. El servidor vuelve a calcular todo (precios,
 * impuestos, cambio): nunca confía en los totales que manda el navegador.
 */
class Ventas
{
    /**
     * Una línea: precio × cantidad − descuento, con impuesto incluido o sumado.
     * @return array{base: float, impuesto: float, total: float, descuento: float}
     */
    public static function linea(float $precio, float $cantidad, float $descuento, float $tasa, bool $incluido, int $dec): array
    {
        $bruto = round($precio * $cantidad, 2);
        $descuento = max(0, min($bruto, round($descuento, 2)));
        $neto = $bruto - $descuento;
        if ($incluido) {
            $total = round($neto, $dec);
            $impuesto = $tasa > 0 ? round($total - $total / (1 + $tasa / 100), 2) : 0.0;
        } else {
            $total = round($neto * (1 + $tasa / 100), $dec);
            $impuesto = $tasa > 0 ? round($total - $total / (1 + $tasa / 100), 2) : 0.0;
        }
        return ['base' => round($total - $impuesto, 2), 'impuesto' => $impuesto, 'total' => $total, 'descuento' => $descuento];
    }

    /**
     * @param array $d items[{producto_id?, descripcion, cantidad, precio, descuento}], descuento_pct, pagos[{metodo, monto, referencia}], cliente_id, cliente_nombre, notas
     */
    public static function registrar(array $d, PosConfig $cfg, PosCaja $caja, User $u): PosVenta
    {
        if (!$u->tienePermiso('pos.vender') || (!$u->esAdmin() && (int)$u->cyber_id !== (int)$cfg->cyber_id)) {
            throw ValidationException::withMessages(['items'=>'No tiene permiso para vender en este Cyber.']);
        }
        return DB::transaction(function () use ($d, $cfg, $caja, $u) {
            // Mismo bloqueo que retiros y cierre: la venta no puede cruzarse con el cierre.
            $caja = PosCaja::whereKey($caja->id)->where('cyber_id', $cfg->cyber_id)->lockForUpdate()->firstOrFail();
            if (!empty($d['clave_operacion'])) {
                $anterior = PosVenta::where('cyber_id', $cfg->cyber_id)->where('clave_operacion', $d['clave_operacion'])->first();
                if ($anterior) {
                    return $anterior;
                }
            }
            if ($caja->estado !== 'abierta') {
                throw ValidationException::withMessages(['pagos' => 'La caja se cerró. Ábrala antes de vender.']);
            }
            if (!$u->tienePermiso('pos.descuentos') && ((float)($d['descuento_pct']??0)>0 || collect($d['items'])->contains(fn ($it)=>(float)($it['descuento']??0)>0))) {
                throw ValidationException::withMessages(['items'=>'Su cuenta no tiene permiso para aplicar descuentos.']);
            }
            $dec = (int) $cfg->decimales;
            $pct = max(0, min(100, (float) ($d['descuento_pct'] ?? 0)));
            $productos = PosProducto::where('cyber_id', $cfg->cyber_id)->whereIn('id', collect($d['items'])->pluck('producto_id')->filter())->where('activo', true)->orderBy('id')->lockForUpdate()->get()->keyBy('id');

            $lineas = [];
            foreach ($d['items'] as $i => $it) {
                $p = !empty($it['producto_id']) ? $productos->get((int) $it['producto_id']) : null;
                if (!empty($it['producto_id']) && !$p) {
                    throw ValidationException::withMessages(["items.$i" => 'Un producto del carrito ya no existe. Recargue la página.']);
                }
                $cant = round((float) $it['cantidad'], 2);
                $precio = round((float) ($it['precio'] ?? $p?->precio ?? 0), 2);
                if (!$u->tienePermiso('pos.precios') && (!$p || abs($precio-(float)$p->precio)>0.004)) {
                    throw ValidationException::withMessages(['items'=>'Su cuenta no puede cambiar precios ni agregar artículos libres.']);
                }
                $tasa = $p ? $p->tasa($cfg) : (float) $cfg->impuesto_tasa;
                $bruto = $precio * $cant;
                $desc = (float) ($it['descuento'] ?? 0);
                $desc += ($bruto - $desc) * $pct / 100; // el descuento general se reparte en cada línea
                $c = self::linea($precio, $cant, $desc, $tasa, (bool) $cfg->precios_con_impuesto, $dec);
                $lineas[] = [
                    'producto' => $p, 'descripcion' => mb_substr(trim($it['descripcion'] ?? '') ?: ($p?->nombre ?? 'Artículo'), 0, 150),
                    'cantidad' => $cant, 'precio' => $precio, 'descuento' => $c['descuento'], 'impuesto_tasa' => $tasa,
                    'impuesto' => $c['impuesto'], 'total' => $c['total'], 'base' => $c['base'], 'costo' => round((float) ($p?->costo ?? 0) * $cant, 2),
                ];
            }

            $total = round(array_sum(array_column($lineas, 'total')), $dec);
            $impuesto = round(array_sum(array_column($lineas, 'impuesto')), 2);
            $descuento = round(array_sum(array_column($lineas, 'descuento')), 2);
            $bruto = round(array_sum(array_map(fn ($l) => $l['precio'] * $l['cantidad'], $lineas)), 2);

            // Pagos: solo el efectivo puede pasarse del total (y se da cambio).
            $pagos = array_values(array_filter($d['pagos'] ?? [], fn ($p) => (float) ($p['monto'] ?? 0) > 0));
            $activos = array_column($cfg->metodosActivos(), 'clave');
            $efectivo = 0.0; $otros = 0.0;
            foreach ($pagos as $p) {
                if (!in_array($p['metodo'], $activos, true)) {
                    throw ValidationException::withMessages(['pagos' => 'Ese método de pago no está activo.']);
                }
                $p['metodo'] === 'efectivo' ? $efectivo += round((float) $p['monto'], 2) : $otros += round((float) $p['monto'], 2);
            }
            $tolerancia = 0.005; // Los pagos conservan centavos aunque los precios se muestren sin decimales.
            if ($otros - $total > $tolerancia) {
                throw ValidationException::withMessages(['pagos' => 'El pago con tarjeta o transferencia no puede ser mayor que el total.']);
            }
            $pagado = round($efectivo + $otros, 2);
            if ($total - $pagado > $tolerancia) {
                throw ValidationException::withMessages(['pagos' => 'Falta ' . $cfg->dinero($total - $pagado) . ' por pagar.']);
            }
            $cambio = max(0, round($pagado - $total, 2));

            // Existencias suficientes.
            $porProducto = [];
            foreach ($lineas as $l) {
                if ($l['producto']?->controla_stock) {
                    $porProducto[$l['producto']->id] = ($porProducto[$l['producto']->id] ?? 0) + $l['cantidad'];
                }
            }
            foreach ($porProducto as $id => $cant) {
                $p = $productos->get($id);
                if ((float) $p->stock < $cant) {
                    throw ValidationException::withMessages(['items' => "No alcanza: de «{$p->nombre}» quedan " . rtrim(rtrim(number_format((float) $p->stock, 2, '.', ''), '0'), '.') . ' (' . rtrim($p->unidad, '.') . ')']);
                }
            }


            [$n, $numero] = $cfg->tomarNumero();
            $venta = PosVenta::create([
                'clave_operacion' => $d['clave_operacion'] ?? null,
                'cyber_id' => $cfg->cyber_id, 'caja_id' => $caja->id, 'user_id' => $u->id,
                'cliente_id' => $d['cliente_id'] ?? null, 'cliente_nombre' => $d['cliente_nombre'] ?? null,
                'consecutivo' => $n, 'numero' => $numero,
                'subtotal' => $bruto, 'descuento' => $descuento, 'impuesto' => $impuesto, 'total' => $total,
                'pagado' => $pagado, 'cambio' => $cambio, 'costo_total' => round(array_sum(array_column($lineas, 'costo')), 2),
                'notas' => $d['notas'] ?? null,
            ]);
            foreach ($lineas as $l) {
                $venta->items()->create(collect($l)->except(['producto', 'base'])->all() + ['producto_id' => $l['producto']?->id]);
                if ($l['producto']) {
                    Inventario::mover($l['producto'], -$l['cantidad'], 'venta', 'Venta ' . $numero, $u->id);
                }
            }
            foreach ($pagos as $p) {
                $venta->pagos()->create(['metodo' => $p['metodo'], 'monto' => round((float) $p['monto'], 2), 'referencia' => $p['referencia'] ?? null]);
            }
            Actividad::registrar('pos_venta', $numero . ' · ' . $cfg->dinero($total));
            return $venta;
        });
    }

    public static function anular(PosVenta $v, User $u, string $motivo): void
    {
        if (!$u->tienePermiso('pos.anular') || (!$u->esAdmin() && (int)$u->cyber_id !== (int)$v->cyber_id)) {
            throw ValidationException::withMessages(['motivo'=>'No tiene permiso para anular ventas de este Cyber.']);
        }
        DB::transaction(function () use ($v, $u, $motivo) {
            $caja = PosCaja::whereKey($v->caja_id)->lockForUpdate()->first();
            $v = PosVenta::whereKey($v->id)->lockForUpdate()->firstOrFail();
            if ($v->estado !== 'pagada') {
                throw ValidationException::withMessages(['motivo' => 'Esta venta ya estaba anulada.']);
            }
            if ($caja && $caja->estado !== 'abierta' && !$u->esAdmin()) {
                throw ValidationException::withMessages(['motivo' => 'La caja ya se cerró. Solo el administrador puede anularla.']);
            }
            $ids = $v->items()->pluck('producto_id')->filter();
            PosProducto::whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get();
            $v->update(['estado' => 'anulada', 'anulada_por' => $u->id, 'anulada_en' => now(), 'motivo_anulacion' => $motivo]);
            foreach ($v->items()->with('producto')->get() as $it) {
                if ($it->producto) {
                    Inventario::mover($it->producto, (float) $it->cantidad, 'anula_venta', 'Anulación ' . $v->numero, $u->id);
                }
            }
        });
        Actividad::registrar('pos_anular', $v->numero . ' · ' . $motivo);
    }
}
