<?php

namespace App\Services\Pos;

use App\Models\Pos\{PosCategoria,PosProveedor};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class CatalogoAuxiliar
{
    public static function revision(Model $row): string
    {
        $data = $row instanceof PosCategoria ? $row->only(['nombre','color'])
            : $row->only(['nombre','identificacion','contacto','telefono','correo','direccion','notas','activo']);
        return hash('sha256',json_encode($data,JSON_THROW_ON_ERROR));
    }

    public static function guardar(Request $request, int $tenant, string $kind, ?Model $row): Model
    {
        if ($kind === 'category') {
            $data = $request->validate([
                'nombre'=>['required','string','max:60'],
                'color'=>['nullable','regex:/^#[0-9a-fA-F]{6}$/'],
            ]);
            $data['color'] = $data['color'] ?? $row?->color ?? '#0B3C74';
            $row ??= new PosCategoria();
        } else {
            $data = $request->validate([
                'nombre'=>['required','string','max:150'],
                'identificacion'=>['nullable','string','max:40'],
                'contacto'=>['nullable','string','max:120'],
                'telefono'=>['nullable','string','max:40'],
                'correo'=>['nullable','email','max:150'],
                'direccion'=>['nullable','string','max:200'],
                'notas'=>['nullable','string','max:1000'],
            ]);
            $data['activo'] = $request->boolean('activo',true);
            $row ??= new PosProveedor();
        }
        $row->fill($data+['cyber_id'=>$tenant])->save();
        return $row;
    }

    public static function borrar(Model $row): bool
    {
        if ($row instanceof PosProveedor && $row->compras()->exists()) {
            $row->update(['activo'=>false]);
            return false;
        }
        $row->delete();
        return true;
    }
}
