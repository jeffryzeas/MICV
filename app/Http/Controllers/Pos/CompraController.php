<?php

namespace App\Http\Controllers\Pos;

use App\Models\Actividad;
use App\Models\Pos\PosCompra;
use App\Models\Pos\PosProducto;
use App\Models\Pos\PosProveedor;
use App\Services\Pos\Compras;
use App\Services\Desktop\LocalBusinessJournal;
use Illuminate\Http\Request;

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
        $reponer = null;
        if ($request->filled('reponer')) {
            $producto = PosProducto::where('cyber_id', $id)->where('activo', true)
                ->where('tipo', 'producto')->where('controla_stock', true)
                ->findOrFail($request->integer('reponer'));
            $cantidad = round((float) $producto->stock_minimo - (float) $producto->stock, 2);
            if ($cantidad > 0) {
                $reponer = ['id' => $producto->id, 'cantidad' => $cantidad, 'proveedor_id' => $producto->proveedor_id];
            }
        }
        return view('pos.compra-form', $c + [
            'reponer' => $reponer,
            'proveedores' => PosProveedor::where('cyber_id', $id)->where('activo', true)->orderBy('nombre')->get(),
            'productos' => PosProducto::where('cyber_id', $id)->where('activo', true)->where('tipo', 'producto')->orderBy('nombre')
                ->get(['id', 'nombre', 'codigo', 'costo', 'stock', 'unidad', 'proveedor_id']),
        ]);
    }

    public function guardar(Request $request)
    {
        $cy = $this->cyber($request);
        $compra = Compras::registrar($cy,$request->user(),$request->all());
        LocalBusinessJournal::record($request,$cy->id,'purchase.create',$compra->id,[
            'proveedor_id'=>$compra->proveedor_id ? LocalBusinessJournal::reference($request,'supplier.create',$compra->proveedor_id) : null,'numero_factura'=>$compra->numero_factura,
            'fecha'=>$compra->fecha->toDateString(),'notas'=>$compra->notas,
            'items'=>LocalBusinessJournal::productReferences($request,$compra->items()->get(['producto_id','cantidad','costo'])->toArray()),
        ]);
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
        Compras::anular($compra,$request->user());
        LocalBusinessJournal::record($request,(int)$compra->cyber_id,'purchase.void',$compra->id,[
            'id'=>LocalBusinessJournal::reference($request,'purchase.create',$compra->id),
        ]);
        Actividad::registrar('pos_compra_anular', '#' . $compra->id);
        return back()->with('mensaje', 'Compra anulada: se rebajó del inventario.');
    }
}
