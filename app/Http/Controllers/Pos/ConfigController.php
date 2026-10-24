<?php

namespace App\Http\Controllers\Pos;

use App\Models\Actividad;
use App\Support\Pais;
use App\Support\PosPaises;
use Illuminate\Http\Request;

class ConfigController extends PosBase
{
    public function editar(Request $request)
    {
        return view('pos.config', $this->comunes($request) + [
            'defectos' => collect(PosPaises::PAISES)->map(fn ($v, $k) => PosPaises::de($k))->all(),
            'paises' => Pais::activos(),
        ]);
    }

    public function actualizar(Request $request)
    {
        $cfg = $this->config($request);
        $d = $request->validate([
            'pais' => ['required', 'string', 'size:2'],
            'moneda' => ['required', 'string', 'size:3'],
            'simbolo' => ['required', 'string', 'max:6'],
            'decimales' => ['required', 'integer', 'in:0,2'],
            'locale' => ['nullable', 'string', 'max:10'],
            'impuesto_nombre' => ['required', 'string', 'max:30'],
            'impuesto_tasa' => ['required', 'numeric', 'min:0', 'max:100'],
            'nombre_comercial' => ['nullable', 'string', 'max:120'],
            'razon_social' => ['nullable', 'string', 'max:150'],
            'id_fiscal_tipo' => ['nullable', 'string', 'max:40'],
            'id_fiscal' => ['nullable', 'string', 'max:40'],
            'telefono' => ['nullable', 'string', 'max:40'],
            'correo' => ['nullable', 'email', 'max:150'],
            'direccion' => ['nullable', 'string', 'max:200'],
            'pie_tiquete' => ['nullable', 'string', 'max:300'],
            'aviso_fiscal' => ['nullable', 'string', 'max:200'],
            'ancho_tiquete' => ['required', 'in:58,80'],
            'prefijo' => ['nullable', 'string', 'max:10', 'regex:/^[A-Za-z0-9\-\/]*$/'],
            'siguiente_numero' => ['required', 'integer', 'min:1', 'max:99999999'],
            'metodos' => ['required', 'array'],
            'metodos.*.nombre' => ['required', 'string', 'max:30'],
        ], ['prefijo.regex' => 'El prefijo solo puede tener letras, números, guion o barra.'], ['impuesto_tasa' => 'tasa', 'siguiente_numero' => 'siguiente número']);

        if (($d['moneda'] !== $cfg->moneda || $d['pais'] !== $cfg->pais) && (\App\Models\Pos\PosVenta::where('cyber_id',$cfg->cyber_id)->exists() || \App\Models\ContableMovimiento::where('cyber_id',$cfg->cyber_id)->exists())) {
            throw \Illuminate\Validation\ValidationException::withMessages(['moneda'=>'No se puede cambiar país o moneda cuando hay movimientos registrados.']);
        }
        // El consecutivo nunca puede bajar a uno ya usado.
        $ultimo = (int) \App\Models\Pos\PosVenta::where('cyber_id', $cfg->cyber_id)->max('consecutivo');
        if ($d['siguiente_numero'] <= $ultimo) {
            return back()->withInput()->withErrors(['siguiente_numero' => "El último comprobante fue el $ultimo: el siguiente debe ser mayor."]);
        }
        $metodos = [];
        foreach (PosPaises::metodos() as $m) {
            $metodos[] = ['clave' => $m['clave'], 'nombre' => $d['metodos'][$m['clave']]['nombre'] ?? $m['nombre'], 'activo' => $m['clave'] === 'efectivo' || $request->boolean("metodos.{$m['clave']}.activo")];
        }
        $d['metodos'] = $metodos;
        $d['precios_con_impuesto'] = $request->boolean('precios_con_impuesto');
        $d['prefijo'] = $d['prefijo'] ?? '';
        $d['locale'] = $d['locale'] ?: (PosPaises::PAISES[$d['pais']][6] ?? 'es');
        \Illuminate\Support\Facades\DB::transaction(function()use($cfg,$d){$cfg->update($d);$cfg->cyber->update(['pais'=>$d['pais']]);});
        Actividad::registrar('pos_config', $cfg->cyber->nombre);
        return back()->with('mensaje', 'Configuración del punto de venta guardada.');
    }
}
