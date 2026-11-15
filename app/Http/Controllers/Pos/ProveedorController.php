<?php

namespace App\Http\Controllers\Pos;

use App\Models\Pos\PosProveedor;
use Illuminate\Http\Request;
use App\Services\Pos\CatalogoAuxiliar;

class ProveedorController extends PosBase
{
    public function index(Request $request)
    {
        $c = $this->comunes($request);
        return view('pos.proveedores', $c + [
            'proveedores' => PosProveedor::where('cyber_id', $c['cyberPos']->id)
                ->when($request->filled('q'), fn ($q) => $q->where('nombre', 'like', '%' . $request->input('q') . '%'))
                ->withCount('productos')->withSum(['compras' => fn ($q) => $q->where('estado', 'registrada')], 'total')
                ->orderByDesc('activo')->orderBy('nombre')->get(),
        ]);
    }

    public function guardar(Request $request)
    {
        $cy = $this->cyber($request);
        $p = $request->filled('id') ? PosProveedor::where('cyber_id',$cy->id)->findOrFail($request->integer('id')) : null;
        CatalogoAuxiliar::guardar($request,$cy->id,'supplier',$p);
        return back()->with('mensaje', 'Proveedor guardado.');
    }

    public function borrar(Request $request, PosProveedor $proveedor)
    {
        $this->delCyber($request, $proveedor);
        $deleted = CatalogoAuxiliar::borrar($proveedor);
        return back()->with('mensaje',$deleted?'Proveedor borrado.':'Tiene compras registradas: se desactivó.');
    }
}
