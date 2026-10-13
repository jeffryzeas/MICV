<?php

namespace App\Http\Controllers\Pos;

use App\Models\Actividad;
use App\Models\Pos\PosCategoria;
use App\Models\Pos\PosMovimiento;
use App\Models\Pos\PosProducto;
use App\Models\Pos\PosProveedor;
use App\Services\Pos\Inventario;
use App\Support\PosPaises;
use App\Support\Archivos;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** Productos, servicios, categorías e inventario (ajustes y kardex). */
class ProductoController extends PosBase
{
    public function foto(Request $request, PosProducto $producto)
    {
        $this->delCyber($request,$producto);
        abort_unless(Archivos::existe($producto->foto),404);
        return response(Storage::disk(Archivos::DISCO)->get($producto->foto),200,[
            'Content-Type'=>Storage::disk(Archivos::DISCO)->mimeType($producto->foto),
            'Cache-Control'=>'private, max-age=3600','Vary'=>'Cookie','X-Content-Type-Options'=>'nosniff',
        ]);
    }
    public function index(Request $request)
    {
        $c = $this->comunes($request);
        $id = $c['cyberPos']->id;
        $q = PosProducto::where('cyber_id', $id)->with('categoria', 'proveedor')
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w->where('nombre', 'like', '%' . $request->input('q') . '%')->orWhere('codigo', $request->input('q'))))
            ->when($request->filled('categoria'), fn ($q) => $q->where('categoria_id', $request->integer('categoria')))
            ->when($request->input('ver') === 'bajo', fn ($q) => $q->where('controla_stock', true)->whereColumn('stock', '<=', 'stock_minimo'))
            ->when($request->input('ver') === 'inactivos', fn ($q) => $q->where('activo', false), fn ($q) => $q->where('activo', true));
        $valor = PosProducto::where('cyber_id', $id)->where('activo', true)->where('controla_stock', true)->selectRaw('COALESCE(SUM(stock * costo),0) costo, COALESCE(SUM(stock * precio),0) venta')->first();
        return view('pos.productos', $c + [
            'productos' => $q->orderBy('nombre')->paginate(40)->withQueryString(),
            'categorias' => PosCategoria::where('cyber_id', $id)->withCount('productos')->orderBy('orden')->orderBy('nombre')->get(),
            'bajos' => PosProducto::where('cyber_id', $id)->where('activo', true)->where('controla_stock', true)->whereColumn('stock', '<=', 'stock_minimo')->count(),
            'valor' => $valor,
            'total' => PosProducto::where('cyber_id', $id)->where('activo', true)->count(),
        ]);
    }

    public function crear(Request $request)
    {
        return $this->formulario($request, new PosProducto(['tipo' => $request->input('tipo', 'producto'), 'activo' => true, 'unidad' => 'unid.']));
    }

    public function editar(Request $request, PosProducto $producto)
    {
        $this->delCyber($request, $producto);
        return $this->formulario($request, $producto);
    }

    protected function formulario(Request $request, PosProducto $p)
    {
        $c = $this->comunes($request);
        return view('pos.producto-form', $c + [
            'p' => $p,
            'categorias' => PosCategoria::where('cyber_id', $c['cyberPos']->id)->orderBy('nombre')->get(),
            'proveedores' => PosProveedor::where('cyber_id', $c['cyberPos']->id)->where('activo', true)->orderBy('nombre')->get(),
        ]);
    }

    public function guardar(Request $request)
    {
        $cy = $this->cyber($request);
        $d = $this->validar($request, $cy->id);
        $inicial = (float) ($d['stock_inicial'] ?? 0);
        unset($d['stock_inicial']);
        $nuevaFoto = $request->hasFile('foto') ? \App\Services\FotoProducto::guardar($request->file('foto')) : null;
        $d['foto']=$nuevaFoto;
        try {
        $p = DB::transaction(function () use ($d, $cy, $inicial, $request) {
            $p = PosProducto::create($d + ['cyber_id' => $cy->id, 'stock' => 0]);
            if ($p->controla_stock && $inicial != 0) {
                Inventario::mover($p, $inicial, 'inicial', 'Al crear el producto', $request->user()->id, (float) $p->costo);
            }
            return $p;
        });
        } catch (\Throwable $e) { Archivos::borrar($nuevaFoto); throw $e; }
        Actividad::registrar('pos_producto_crear', $p->nombre);
        return redirect()->route($request->boolean('otro') ? 'pos.productos.crear' : 'pos.productos', $request->boolean('otro') ? ['tipo' => $p->tipo] : [])
            ->with('mensaje', '«' . $p->nombre . '» guardado.');
    }

    public function actualizar(Request $request, PosProducto $producto)
    {
        $this->delCyber($request, $producto);
        $d = $this->validar($request, $producto->cyber_id, $producto->id);
        unset($d['stock_inicial']);
        $anterior=$producto->foto;
        $nuevaFoto=$request->hasFile('foto') ? \App\Services\FotoProducto::guardar($request->file('foto')) : null;
        if ($nuevaFoto || $request->boolean('quitar_foto')) $d['foto']=$nuevaFoto;
        try { $producto->update($d); }
        catch (\Throwable $e) { Archivos::borrar($nuevaFoto); throw $e; }
        if (array_key_exists('foto',$d) && $anterior!==$d['foto']) Archivos::borrar($anterior);
        Actividad::registrar('pos_producto_editar', $producto->nombre);
        return redirect()->route('pos.productos')->with('mensaje', '«' . $producto->nombre . '» actualizado.');
    }

    /** Con ventas o movimientos no se borra: se desactiva (así no se pierde el historial). */
    public function borrar(Request $request, PosProducto $producto)
    {
        $this->delCyber($request, $producto);
        if ($producto->movimientos()->exists() || \App\Models\Pos\PosVentaItem::where('producto_id', $producto->id)->exists()) {
            $producto->update(['activo' => false]);
            return back()->with('mensaje', '«' . $producto->nombre . '» tiene historial: se desactivó (ya no sale en la caja).');
        }
        $foto=$producto->foto;
        $producto->delete();
        Archivos::borrar($foto);
        return back()->with('mensaje', 'Producto borrado.');
    }

    public function ajuste(Request $request, PosProducto $producto)
    {
        $this->delCyber($request, $producto);
        $d = $request->validate([
            'modo' => ['required', 'in:sumar,restar,fijar'],
            'cantidad' => ['required', 'numeric', 'min:0', 'max:1000000'],
            'motivo' => ['required', 'string', 'max:120'],
        ]);
        $cant = DB::transaction(function () use ($producto, $d, $request) {
            $producto = PosProducto::whereKey($producto->id)->lockForUpdate()->firstOrFail();
            if (!$producto->controla_stock) {
                $producto->update(['controla_stock' => true]);
            }
            $cant = match ($d['modo']) { 'sumar' => (float) $d['cantidad'], 'restar' => -(float) $d['cantidad'], 'fijar' => (float) $d['cantidad'] - (float) $producto->stock };
            if ($cant != 0) {
                Inventario::mover($producto, $cant, 'ajuste', $d['motivo'], $request->user()->id);
            }
            return $cant;
        });
        Actividad::registrar('pos_ajuste', $producto->nombre . ' ' . ($cant > 0 ? '+' : '') . $cant . ' · ' . $d['motivo']);
        return back()->with('mensaje', 'Inventario de «' . $producto->nombre . '» ajustado.');
    }

    public function kardex(Request $request, PosProducto $producto)
    {
        $this->delCyber($request, $producto);
        return view('pos.kardex', $this->comunes($request) + [
            'p' => $producto,
            'movs' => PosMovimiento::where('producto_id', $producto->id)->with('usuario')->latest('id')->paginate(50),
        ]);
    }

    // ── Categorías (desde la misma pantalla de productos)

    public function categoria(Request $request)
    {
        $cy = $this->cyber($request);
        $d = $request->validate(['id' => ['nullable', 'integer'], 'nombre' => ['required', 'string', 'max:60'], 'color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/']]);
        if (!empty($d['id'])) {
            $cat = PosCategoria::where('cyber_id', $cy->id)->findOrFail($d['id']);
            $cat->update(['nombre' => $d['nombre'], 'color' => $d['color'] ?? $cat->color]);
        } else {
            PosCategoria::create(['cyber_id' => $cy->id, 'nombre' => $d['nombre'], 'color' => $d['color'] ?? '#0B3C74']);
        }
        return back()->with('mensaje', 'Categoría guardada.');
    }

    public function categoriaBorrar(Request $request, PosCategoria $categoria)
    {
        $this->delCyber($request, $categoria);
        $categoria->delete(); // los productos quedan "sin categoría"
        return back()->with('mensaje', 'Categoría borrada.');
    }

    /** Crea de una vez los servicios típicos de un Cyber (sin precio, para completarlos). */
    public function sugeridos(Request $request)
    {
        $cy = $this->cyber($request);
        $n = 0;
        foreach (PosPaises::SERVICIOS_SUGERIDOS as [$nombre, $cat]) {
            if (PosProducto::where('cyber_id', $cy->id)->where('nombre', $nombre)->exists()) {
                continue;
            }
            $categoria = PosCategoria::firstOrCreate(['cyber_id' => $cy->id, 'nombre' => $cat], ['color' => $cat === 'Servicios de CV' ? '#0B3C74' : ($cat === 'Impresiones' ? '#0E9F6E' : '#7C3AED')]);
            PosProducto::create(['cyber_id' => $cy->id, 'categoria_id' => $categoria->id, 'tipo' => 'servicio', 'nombre' => $nombre, 'precio' => 0, 'favorito' => true, 'unidad' => 'serv.']);
            $n++;
        }
        return back()->with('mensaje', $n ? "Se agregaron $n servicios. Póngales precio tocando cada uno." : 'Ya tenía todos los servicios sugeridos.');
    }

    protected function validar(Request $request, int $cyberId, ?int $id = null): array
    {
        $d = $request->validate([
            'foto'=>['nullable','image','mimes:jpg,jpeg,png,webp','max:51200'],
            'quitar_foto'=>['nullable','boolean'],
            'tipo' => ['required', 'in:producto,servicio'],
            'nombre' => ['required', 'string', 'max:150'],
            'codigo' => ['nullable', 'string', 'max:60', Rule::unique('pos_productos', 'codigo')->where('cyber_id', $cyberId)->ignore($id)],
            'categoria_id' => ['nullable', Rule::exists('pos_categorias', 'id')->where('cyber_id', $cyberId)],
            'proveedor_id' => ['nullable', Rule::exists('pos_proveedores', 'id')->where('cyber_id', $cyberId)],
            'precio' => ['required', 'numeric', 'min:0', 'max:999999999'],
            'costo' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'impuesto' => ['required', 'in:general,exento,propio'],
            'impuesto_tasa' => ['nullable', 'required_if:impuesto,propio', 'numeric', 'min:0', 'max:100'],
            'stock_inicial' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'stock_minimo' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'unidad' => ['nullable', 'string', 'max:15'],
        ], [], ['codigo' => 'código', 'precio' => 'precio de venta', 'impuesto_tasa' => 'tasa de impuesto']);
        unset($d['foto'],$d['quitar_foto']);
        $d['exento'] = $d['impuesto'] === 'exento';
        $d['impuesto_tasa'] = $d['impuesto'] === 'propio' ? $d['impuesto_tasa'] : null;
        unset($d['impuesto']);
        $d['costo'] = $d['costo'] ?? 0;
        $d['stock_minimo'] = $d['stock_minimo'] ?? 0;
        $d['unidad'] = ($d['unidad'] ?? null) ?: ($d['tipo'] === 'servicio' ? 'serv.' : 'unid.');
        $d['controla_stock'] = $d['tipo'] === 'producto' && $request->boolean('controla_stock');
        $d['favorito'] = $request->boolean('favorito');
        $d['activo'] = $request->boolean('activo', true);
        $d['codigo'] = filled($d['codigo'] ?? null) ? trim($d['codigo']) : null;
        return $d;
    }
}
