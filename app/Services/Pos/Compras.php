<?php

namespace App\Services\Pos;

use App\Models\{Cyber,User};
use App\Models\Pos\{PosCompra,PosProducto};
use Illuminate\Support\Facades\{DB,Validator};
use Illuminate\Validation\Rule;

class Compras
{
    public static function registrar(Cyber $cy, User $user, array $fields): PosCompra
    {
        $d = Validator::make($fields, [
            'proveedor_id' => ['nullable', Rule::exists('pos_proveedores', 'id')->where('cyber_id', $cy->id)],
            'numero_factura' => ['nullable', 'string', 'max:60'],
            'fecha' => ['required', 'date', 'before_or_equal:today'],
            'notas' => ['nullable', 'string', 'max:300'],
            'items' => ['required', 'array', 'min:1', 'max:300'],
            'items.*.producto_id' => ['required', Rule::exists('pos_productos', 'id')->where('cyber_id', $cy->id)],
            'items.*.cantidad' => ['required', 'numeric', 'min:0.01', 'max:1000000'],
            'items.*.costo' => ['required', 'numeric', 'min:0', 'max:999999999'],
        ])->validate();
        return DB::transaction(function () use ($d,$cy,$user) {
            $compra = PosCompra::create(collect($d)->except('items')->all() + ['cyber_id' => $cy->id, 'user_id' => $user->id, 'total' => 0]);
            $productos = PosProducto::where('cyber_id', $cy->id)->whereIn('id', array_column($d['items'], 'producto_id'))
                ->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $total = 0;
            foreach ($d['items'] as $it) {
                $p = $productos->get($it['producto_id']);
                abort_unless($p, 422, 'El producto ya no existe. Recargue la compra.');
                $sub = round($it['cantidad'] * $it['costo'], 2);
                $total += $sub;
                $compra->items()->create(['producto_id' => $p->id, 'descripcion' => $p->nombre, 'cantidad' => $it['cantidad'], 'costo' => $it['costo'], 'subtotal' => $sub]);
                if (!$p->controla_stock) {
                    $p->update(['controla_stock' => true]);
                }
                Inventario::mover($p, (float) $it['cantidad'], 'compra', 'Compra #' . $compra->id . ($compra->numero_factura ? ' · fact. ' . $compra->numero_factura : ''), $user->id, (float) $it['costo']);
                if (!$p->proveedor_id && $compra->proveedor_id) {
                    $p->update(['proveedor_id' => $compra->proveedor_id]);
                }
            }
            $compra->update(['total' => $total]);
            return $compra;
        });
    }

    public static function anular(PosCompra $compra, User $user): void
    {
        DB::transaction(function () use ($compra, $user) {
            $compra = PosCompra::whereKey($compra->id)->lockForUpdate()->firstOrFail();
            abort_unless($compra->estado === 'registrada', 422, 'Esta compra ya estaba anulada.');
            PosProducto::whereIn('id', $compra->items()->pluck('producto_id')->filter())->orderBy('id')->lockForUpdate()->get();
            foreach ($compra->items()->with('producto')->get() as $it) {
                if ($it->producto) {
                    Inventario::mover($it->producto, -(float) $it->cantidad, 'anula_compra', 'Anulación compra #' . $compra->id, $user->id);
                }
            }
            $compra->update(['estado' => 'anulada']);
        });
    }
}
