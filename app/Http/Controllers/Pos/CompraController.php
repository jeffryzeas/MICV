<?php

namespace App\Http\Controllers\Pos;

use App\Models\Actividad;
use App\Models\Pos\PosCompra;
use App\Models\Pos\PosProducto;
use App\Models\Pos\PosProveedor;
use App\Services\Pos\Inventario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** Compras a proveedores: suben el inventario y actualizan el costo promedio. */
class CompraController extends PosBase
{
    public function index(Request $request)
    {
        $c = $this->comunes($request);
        return view('pos.compras', $c + [
            'compras' => PosCompra::where('cyber_id', $c['cyberPos']->id)->with('proveedor', 'usuario')->withCount('items')->latest('fecha')->latest('id')->paginate(30),
        ]);
    }

    public function crear(Request $request)
    {
        $c = $this->comunes($request);
        $id = $c['cyberPos']->id;
        return view('pos.compra-form', $c + [
            'proveedores' => PosProveedor::where('cyber_id', $id)->where('activo', true)->orderBy('nombre')->get(),
            'productos' => PosProducto::where('cyber_id', $id)->where('activo', true)->where('tipo', 'producto')->orderBy('nombre')
                ->get(['id', 'nombre', 'codigo', 'costo', 'stock', 'unidad', 'proveedor_id']),
        ]);
    }

    public function guardar(Request $request)
    {
        $cy = $this->cyber($request);
        $d = $request->validate([
            'proveedor_id' => ['nullable', Rule::exists('pos_proveedores', 'id')->where('cyber_id', $cy->id)],
            'numero_factura' => ['nullable', 'string', 'max:60'],
            'fecha' => ['required', 'date', 'before_or_equal:today'],
            'notas' => ['nullable', 'string', 'max:300'],
            'items' => ['required', 'array', 'min:1', 'max:300'],
            'items.*.producto_id' => ['required', Rule::exists('pos_productos', 'id')->where('cyber_id', $cy->id)],
            'items.*.cantidad' => ['required', 'numeric', 'min:0.01', 'max:1000000'],
            'items.*.costo' => ['required', 'numeric', 'min:0', 'max:999999999'],
        ], [], ['items' => 'productos', 'items.*.producto_id' => 'producto', 'items.*.costo' => 'costo']);

        $compra = DB::transaction(function () use ($d, $cy, $request) {
            $compra = PosCompra::create(collect($d)->except('items')->all() + ['cyber_id' => $cy->id, 'user_id' => $request->user()->id, 'total' => 0]);
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
                Inventario::mover($p, (float) $it['cantidad'], 'compra', 'Compra #' . $compra->id . ($compra->numero_factura ? ' · fact. ' . $compra->numero_factura : ''), $request->user()->id, (float) $it['costo']);
                if (!$p->proveedor_id && $compra->proveedor_id) {
                    $p->update(['proveedor_id' => $compra->proveedor_id]);
                }
            }
            $compra->update(['total' => $total]);
            return $compra;
        });
        Actividad::registrar('pos_compra', '#' . $compra->id . ' · ' . $this->config($request)->dinero($compra->total));
        return redirect()->route('pos.compras.ver', $compra)->with('mensaje', 'Compra registrada. El inventario ya se actualizó.');
    }

    public function ver(Request $request, PosCompra $compra)
    {
        $this->delCyber($request, $compra);
        return view('pos.compra', $this->comunes($request) + ['compra' => $compra->load('items.producto', 'proveedor', 'usuario')]);
    }

    public function anular(Request $request, PosCompra $compra)
    {
        $this->delCyber($request, $compra);
        abort_unless($compra->estado === 'registrada', 422);
        DB::transaction(function () use ($compra, $request) {
            $compra = PosCompra::whereKey($compra->id)->lockForUpdate()->firstOrFail();
            abort_unless($compra->estado === 'registrada', 422, 'Esta compra ya estaba anulada.');
            PosProducto::whereIn('id', $compra->items()->pluck('producto_id')->filter())->orderBy('id')->lockForUpdate()->get();
            foreach ($compra->items()->with('producto')->get() as $it) {
                if ($it->producto) {
                    Inventario::mover($it->producto, -(float) $it->cantidad, 'anula_compra', 'Anulación compra #' . $compra->id, $request->user()->id);
                }
            }
            $compra->update(['estado' => 'anulada']);
        });
        Actividad::registrar('pos_compra_anular', '#' . $compra->id);
        return back()->with('mensaje', 'Compra anulada: se rebajó del inventario.');
    }
}
