<?php

namespace App\Http\Controllers\Pos;

use App\Models\Cliente;
use App\Models\Pos\PosCaja;
use App\Models\Pos\PosCategoria;
use App\Models\Pos\PosProducto;
use App\Models\Pos\PosPendiente;
use App\Models\Pos\PosVenta;
use App\Services\Pos\Ventas;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Pantalla de venta (la caja registradora). */
class VenderController extends PosBase
{
    public function index(Request $request)
    {
        $c = $this->comunes($request);
        $idPendiente = $request->validate(['pendiente' => ['nullable', 'uuid']])['pendiente'] ?? null;
        $caja = PosCaja::abiertaDe($c['cyberPos']->id);
        if (!$caja) {
            return view('pos.abrir', $c + ['pendiente' => $idPendiente,
                'ultima' => PosCaja::where('cyber_id', $c['cyberPos']->id)->where('estado', 'cerrada')->latest('id')->first()]);
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
        $pendientePre = null;
        if ($idPendiente) {
            $pendiente = PosPendiente::where('cyber_id', $cfg->cyber_id)
                ->where('user_id', $request->user()->id)->whereNull('venta_id')->findOrFail($idPendiente);
            $contenido = $pendiente->contenido;
            abort_if($contenido['moneda'] !== $cfg->moneda, 409, 'La moneda del pendiente difiere de la moneda actual del POS.');
            $carrito = $contenido['carrito'];
            $cl = empty($carrito['cliente_id']) ? null : Cliente::find($carrito['cliente_id']);
            $pendientePre = [
                'id' => $pendiente->id,
                'nombre' => $contenido['nombre'],
                'carrito' => $carrito,
                'cliente' => $cl?->esVisiblePara($request->user()) ? ['id' => $cl->id, 'texto' => $cl->nombreCompleto()] : null,
            ];
        }
        return view('pos.vender', $c + [
            'caja' => $caja,
            'productos' => $productos,
            'categorias' => PosCategoria::where('cyber_id', $c['cyberPos']->id)->orderBy('orden')->orderBy('nombre')->get(['id', 'nombre', 'color']),
            'clientePre' => $cliente,
            'pendientePre' => $pendientePre,
        ]);
    }

    public function guardar(Request $request)
    {
        $cfg = $this->config($request);
        $caja = PosCaja::abiertaDe($cfg->cyber_id);
        if (!$caja) {
            return response()->json(['ok' => false, 'mensaje' => 'La caja está cerrada. Ábrala para vender.'], 422);
        }
        $d = $request->validate(Ventas::reglasItems() + [
            'clave_operacion' => ['required_with:pendiente_id', 'nullable', 'uuid'],
            'pendiente_id' => ['nullable', 'uuid'],
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
        $venta = DB::transaction(function () use ($request, $cfg, $caja, $d) {
            $pendiente = empty($d['pendiente_id']) ? null : PosPendiente::where('cyber_id', $cfg->cyber_id)
                ->where('user_id', $request->user()->id)->lockForUpdate()->findOrFail($d['pendiente_id']);
            if ($pendiente?->venta_id) {
                abort_unless(PosVenta::whereKey($pendiente->venta_id)->where('cyber_id', $cfg->cyber_id)
                    ->where('clave_operacion', $d['clave_operacion'])->exists(), 409, 'Este pendiente ya se cobró.');
            } elseif ($pendiente) {
                abort_if($pendiente->contenido['moneda'] !== $cfg->moneda, 409, 'La moneda del pendiente difiere de la moneda actual del POS.');
            }
            $venta = Ventas::registrar($d, $cfg, $caja, $request->user());
            if ($pendiente && !$pendiente->venta_id) {
                $pendiente->venta_id = $venta->id;
                PosPendiente::withoutTimestamps(fn () => $pendiente->save());
            }
            unset($d['pendiente_id']);
            $journalItems = \App\Services\Desktop\LocalBusinessJournal::productReferences($request,$d['items']);
            \App\Services\Desktop\LocalBusinessJournal::record($request,(int)$cfg->cyber_id,'sale.create',$venta->id,
                ['items'=>$journalItems,'cliente_id'=>empty($d['cliente_id']) ? null
                    : \App\Services\Desktop\LocalBusinessJournal::reference($request,'client.full.create',(int)$d['cliente_id'])]+$d+[
                    'caja_id'=>\App\Services\Desktop\LocalBusinessJournal::reference($request,'cash.open',$caja->id),
                    'total'=>$venta->total,'moneda'=>$cfg->moneda,
                ],$d['clave_operacion']??null);

            return $venta;
        });

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
