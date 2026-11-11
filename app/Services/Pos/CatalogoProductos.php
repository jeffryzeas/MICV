<?php

namespace App\Services\Pos;

use App\Models\Pos\{PosProducto,PosVentaItem};
use App\Services\FotoProducto;
use App\Support\Archivos;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CatalogoProductos
{
    public static function revision(PosProducto $product): string
    {
        $fields = $product->only(['tipo','nombre','codigo','categoria_id','proveedor_id','precio','costo',
            'exento','impuesto_tasa','stock_minimo','unidad','controla_stock','favorito','activo']);
        // The same category/supplier may have a different numeric ID after sync.
        $fields['categoria_id'] = $product->categoria ? CatalogoAuxiliar::revision($product->categoria) : null;
        $fields['proveedor_id'] = $product->proveedor?->only(['nombre','activo']);
        return hash('sha256',json_encode($fields,JSON_THROW_ON_ERROR));
    }

    public static function crear(Request $request, int $tenant): PosProducto
    {
        $data = self::validar($request,$tenant);
        $initial = (float)($data['stock_inicial'] ?? 0);
        unset($data['stock_inicial']);
        $photo = $request->hasFile('foto') ? FotoProducto::guardar($request->file('foto')) : null;
        try {
            return DB::transaction(function () use ($request,$tenant,$data,$initial,$photo) {
                $product = PosProducto::create($data+['cyber_id'=>$tenant,'stock'=>0,'foto'=>$photo]);
                if ($product->controla_stock && $initial != 0) {
                    Inventario::mover($product,$initial,'inicial','Al crear el producto',$request->user()->id,(float)$product->costo);
                }
                return $product;
            });
        } catch (\Throwable $error) {
            Archivos::borrar($photo);
            throw $error;
        }
    }

    public static function editar(Request $request, PosProducto $product): void
    {
        $data = self::validar($request,(int)$product->cyber_id,$product->id);
        unset($data['stock_inicial']);
        $previous = $product->foto;
        $photo = $request->hasFile('foto') ? FotoProducto::guardar($request->file('foto')) : null;
        if ($photo || $request->boolean('quitar_foto')) $data['foto'] = $photo;
        try {
            $product->update($data);
        } catch (\Throwable $error) {
            Archivos::borrar($photo);
            throw $error;
        }
        if (array_key_exists('foto',$data) && $previous !== $photo) {
            DB::afterCommit(fn()=>Archivos::borrar($previous));
        }
    }

    public static function borrar(PosProducto $product): bool
    {
        if ($product->movimientos()->exists() || PosVentaItem::where('producto_id',$product->id)->exists()) {
            $product->update(['activo'=>false]);
            return false;
        }
        $photo = $product->foto;
        $product->delete();
        DB::afterCommit(fn()=>Archivos::borrar($photo));
        return true;
    }

    private static function validar(Request $request, int $cyberId, ?int $id = null): array
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
