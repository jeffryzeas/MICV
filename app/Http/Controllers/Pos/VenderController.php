<?php

namespace App\Http\Controllers\Pos;

use App\Models\Cliente;
use App\Models\Pos\PosCaja;
use App\Models\Pos\PosCategoria;
use App\Models\Pos\PosProducto;
use App\Services\Pos\Ventas;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Pantalla de venta (la caja registradora). */
class VenderController extends PosBase
{
    public function index(Request $request)
    {
        $c = $this->comunes($request);
        $caja = PosCaja::abiertaDe($c['cyberPos']->id);
        if (!$caja) {
            return view('pos.abrir', $c + ['ultima' => PosCaja::where('cyber_id', $c['cyberPos']->id)->where('estado', 'cerrada')->latest('id')->first()]);
        }
        $cfg = $c['cfg'];
        $productos = PosProducto::where('cyber_id', $c['cyberPos']->id)->where('activo', true)->orderByDesc('favorito')->orderBy('nombre')->get()
            ->map(fn ($p) => [
                'foto' => $p->fotoUrl(), 'id' => $p->id, 'n' => $p->nombre, 'c' => $p->codigo, 'p' => (float) $p->precio, 't' => $p->tipo, 'cat' => $p->categoria_id,
                's' => $p->controla_stock ? (float) $p->stock : null, 'u' => $p->unidad, 'f' => $p->favorito, 'tasa' => $p->tasa($cfg),
            ]);
        $cliente = null;
        if ($request->filled('cliente')) {
            $cl = Cliente::find($request->integer('cliente'));
            if ($cl && $cl->esVisiblePara($request->user())) {
                $cliente = ['id' => $cl->id, 'texto' => $cl->nombreCompleto() . ' · ' . ($cl->documentoFormateado() ?: '')];
            }
        }
        return view('pos.vender', $c + [
            'caja' => $caja,
            'productos' => $productos,
            'categorias' => PosCategoria::where('cyber_id', $c['cyberPos']->id)->orderBy('orden')->orderBy('nombre')->get(['id', 'nombre', 'color']),
            'clientePre' => $cliente,
        ]);
    }

    public function guardar(Request $request)
    {
        $cfg = $this->config($request);
        $caja = PosCaja::abiertaDe($cfg->cyber_id);
        if (!$caja) {
            return response()->json(['ok' => false, 'mensaje' => 'La caja está cerrada. Ábrala para vender.'], 422);
        }
        $d = $request->validate([
            'clave_operacion' => ['nullable', 'uuid'],
            'items'                => ['required', 'array', 'min:1', 'max:200'],
            'items.*.producto_id'  => ['nullable', 'integer'],
            'items.*.descripcion'  => ['nullable', 'string', 'max:150'],
            'items.*.cantidad'     => ['required', 'numeric', 'min:0.01', 'max:100000'],
            'items.*.precio'       => ['required', 'numeric', 'min:0', 'max:999999999'],
            'items.*.descuento'    => ['nullable', 'numeric', 'min:0'],
            'descuento_pct'        => ['nullable', 'numeric', 'min:0', 'max:100'],
            'pagos'                => ['required', 'array', 'min:1', 'max:5'],
            'pagos.*.metodo'       => ['required', 'string', 'max:20'],
            'pagos.*.monto'        => ['required', 'numeric', 'min:0'],
            'pagos.*.referencia'   => ['nullable', 'string', 'max:60'],
            'cliente_id'           => ['nullable', 'integer'],
            'cliente_nombre'       => ['nullable', 'string', 'max:150'],
            'notas'                => ['nullable', 'string', 'max:300'],
        ], [], ['items' => 'productos', 'pagos' => 'pago']);

        if (!empty($d['cliente_id'])) {
            $cl = Cliente::find($d['cliente_id']);
            if (!$cl || !$cl->esVisiblePara($request->user())) {
                $d['cliente_id'] = null;
            }
        }
        $venta = Ventas::registrar($d, $cfg, $caja, $request->user());

        return response()->json([
            'ok' => true,
            'venta' => ['id' => $venta->id, 'numero' => $venta->numero, 'total' => $cfg->dinero($venta->total), 'cambio' => (float) $venta->cambio, 'cambioTxt' => $cfg->dinero($venta->cambio)],
            'urls' => [
                'tiquete' => route('pos.ventas.tiquete', $venta),
                'imagen'  => route('pos.ventas.imagen', $venta),
                'correo'  => route('pos.ventas.correo', $venta),
                'ver'     => route('pos.ventas.show', $venta),
            ],
            'correo' => $venta->cliente?->correo,
            // Existencias nuevas para actualizar la pantalla sin recargar.
            'stock' => PosProducto::where('cyber_id', $cfg->cyber_id)->whereIn('id', collect($d['items'])->pluck('producto_id')->filter())->where('controla_stock', true)->pluck('stock', 'id')->map(fn ($s) => (float) $s),
        ]);
    }
}
