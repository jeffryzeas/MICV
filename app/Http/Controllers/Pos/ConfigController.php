<?php

namespace App\Http\Controllers\Pos;

use App\Models\{Actividad, ContableMovimiento};
use App\Models\Pos\{PosConfig, PosVenta};
use App\Services\Desktop\{FullClientSync, LocalBusinessJournal};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
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
        $this->aplicar($request, $cfg);
        Actividad::registrar('pos_config', $cfg->cyber->nombre);
        return back()->with('mensaje', 'Configuración del punto de venta guardada.');
    }

    public static function revision(PosConfig $config): string
    {
        return FullClientSync::fingerprint($config->only(array_diff($config->getFillable(), ['cyber_id'])));
    }

    public function aplicar(Request $request, PosConfig $config, ?string $revision = null): void
    {
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

        $metodos = [];
        foreach (PosPaises::metodos() as $m) {
            $metodos[] = ['clave' => $m['clave'], 'nombre' => $d['metodos'][$m['clave']]['nombre'] ?? $m['nombre'], 'activo' => $m['clave'] === 'efectivo' || $request->boolean("metodos.{$m['clave']}.activo")];
        }
        $d['metodos'] = $metodos;
        $d['precios_con_impuesto'] = $request->boolean('precios_con_impuesto');
        $d['prefijo'] = $d['prefijo'] ?? '';
        $d['locale'] = ($d['locale'] ?? null) ?: (PosPaises::PAISES[$d['pais']][6] ?? 'es');
        DB::transaction(function () use ($request, $config, $revision, $d) {
            $cfg = PosConfig::query()->whereKey($config->id)->lockForUpdate()->firstOrFail();
            $actual = self::revision($cfg);
            abort_if($revision !== null && !hash_equals($actual, $revision), 409,
                'La configuración del punto de venta cambió en la web. Revise los valores antes de sincronizar.');
            if (($d['moneda'] !== $cfg->moneda || $d['pais'] !== $cfg->pais)
                && (PosVenta::where('cyber_id', $cfg->cyber_id)->exists()
                    || ContableMovimiento::where('cyber_id', $cfg->cyber_id)->exists())) {
                throw ValidationException::withMessages(['moneda' => 'No se puede cambiar país o moneda cuando hay movimientos registrados.']);
            }
            $ultimo = (int) PosVenta::where('cyber_id', $cfg->cyber_id)->max('consecutivo');
            if ($d['siguiente_numero'] <= $ultimo) {
                throw ValidationException::withMessages(['siguiente_numero' => "El último comprobante fue el $ultimo: el siguiente debe ser mayor."]);
            }
            $cfg->update($d);
            $cfg->cyber->update(['pais' => $d['pais']]);
            if ($actual !== self::revision($cfg->refresh())) {
                LocalBusinessJournal::record($request, $cfg->cyber_id, 'pos.config.update', $cfg->id, [
                    'revision' => $actual,
                    'config' => array_replace($d, ['metodos' => array_column($d['metodos'], null, 'clave')]),
                ]);
            }
        });
    }
}
