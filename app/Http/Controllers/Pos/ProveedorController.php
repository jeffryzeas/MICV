<?php

namespace App\Http\Controllers\Pos;

use App\Models\Pos\PosProveedor;
use Illuminate\Http\Request;

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
        $d = $this->validar($request);
        if ($request->filled('id')) {
            $p = PosProveedor::where('cyber_id', $cy->id)->findOrFail($request->integer('id'));
            $p->update($d);
        } else {
            PosProveedor::create($d + ['cyber_id' => $cy->id]);
        }
        return back()->with('mensaje', 'Proveedor guardado.');
    }

    public function borrar(Request $request, PosProveedor $proveedor)
    {
        $this->delCyber($request, $proveedor);
        if ($proveedor->compras()->exists()) {
            $proveedor->update(['activo' => false]);
            return back()->with('mensaje', 'Tiene compras registradas: se desactivó.');
        }
        $proveedor->delete();
        return back()->with('mensaje', 'Proveedor borrado.');
    }

    protected function validar(Request $request): array
    {
        $d = $request->validate([
            'nombre' => ['required', 'string', 'max:150'],
            'identificacion' => ['nullable', 'string', 'max:40'],
            'contacto' => ['nullable', 'string', 'max:120'],
            'telefono' => ['nullable', 'string', 'max:40'],
            'correo' => ['nullable', 'email', 'max:150'],
            'direccion' => ['nullable', 'string', 'max:200'],
            'notas' => ['nullable', 'string', 'max:1000'],
        ]);
        $d['activo'] = $request->boolean('activo', true);
        return $d;
    }
}
