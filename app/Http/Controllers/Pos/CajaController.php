<?php

namespace App\Http\Controllers\Pos;

use App\Models\Actividad;
use App\Models\Pos\PosCaja;
use App\Support\PosPaises;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Abrir y cerrar caja, entradas y salidas de efectivo, arqueo e historial. */
class CajaController extends PosBase
{
    public function abrir(Request $request)
    {
        $cy = $this->cyber($request);
        $d = $request->validate(['monto_inicial' => ['required', 'numeric', 'min:0', 'max:999999999']], [], ['monto_inicial' => 'monto inicial']);
        $creada = DB::transaction(function () use ($cy, $request, $d) {
            // Se bloquea el negocio incluso si todavía no existe una caja abierta.
            \App\Models\Cyber::whereKey($cy->id)->lockForUpdate()->firstOrFail();
            if (PosCaja::where('cyber_id', $cy->id)->where('estado', 'abierta')->lockForUpdate()->first()) {
                return false;
            }
            PosCaja::create(['cyber_id' => $cy->id, 'user_id' => $request->user()->id, 'abierta_en' => now(), 'monto_inicial' => $d['monto_inicial']]);
            return true;
        });
        if (!$creada) {
            return redirect()->route('pos.vender');
        }
        Actividad::registrar('pos_caja_abrir', $this->config($request)->dinero($d['monto_inicial']));
        return redirect()->route('pos.vender')->with('mensaje', 'Caja abierta. ¡Buenas ventas!');
    }

    /** Turno actual: resumen, movimientos y cierre. */
    public function actual(Request $request)
    {
        $c = $this->comunes($request);
        $caja = PosCaja::abiertaDe($c['cyberPos']->id);
        if (!$caja) {
            return redirect()->route('pos.vender');
        }
        return view('pos.caja', $c + [
            'caja' => $caja->load('usuario'),
            'r' => $caja->resumen(),
            'movs' => $caja->movimientos()->with('usuario')->latest()->get(),
            'ventas' => $caja->ventas()->with('usuario', 'cliente')->latest()->limit(15)->get(),
            'billetes' => PosPaises::denominaciones($c['cfg']->moneda),
        ]);
    }

    public function movimiento(Request $request)
    {
        $cfg = $this->config($request);
        $caja = PosCaja::abiertaDe($cfg->cyber_id);
        abort_unless($caja, 422, 'No hay caja abierta.');
        $d = $request->validate([
            'tipo' => ['required', 'in:ingreso,retiro'],
            'monto' => ['required', 'numeric', 'min:0.01', 'max:999999999'],
            'motivo' => ['required', 'string', 'max:200'],
        ]);
        DB::transaction(function () use ($caja, $d, $request) {
            $caja = PosCaja::whereKey($caja->id)->lockForUpdate()->firstOrFail();
            if ($caja->estado !== 'abierta') {
                throw ValidationException::withMessages(['monto' => 'Esta caja ya se cerró.']);
            }
            if ($d['tipo'] === 'retiro' && round((float) $d['monto'], 2) > $caja->resumen()['esperado'] + 0.001) {
                throw ValidationException::withMessages(['monto' => 'No hay tanto efectivo en la caja.']);
            }
            $caja->movimientos()->create($d + ['user_id' => $request->user()->id]);
        });
        Actividad::registrar('pos_caja_' . $d['tipo'], $cfg->dinero($d['monto']) . ' · ' . $d['motivo']);
        return back()->with('mensaje', $d['tipo'] === 'ingreso' ? 'Entrada de efectivo registrada.' : 'Salida de efectivo registrada.');
    }

    public function cerrar(Request $request)
    {
        $cfg = $this->config($request);
        $caja = PosCaja::abiertaDe($cfg->cyber_id);
        abort_unless($caja, 422, 'No hay caja abierta.');
        $d = $request->validate([
            'conteo' => ['nullable', 'array'],
            'conteo.*' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'efectivo_contado' => ['required', 'numeric', 'min:0', 'max:999999999'],
            'notas_cierre' => ['nullable', 'string', 'max:300'],
        ], [], ['efectivo_contado' => 'efectivo contado']);
        $r = DB::transaction(function () use ($caja, $d, $request) {
            $caja = PosCaja::whereKey($caja->id)->lockForUpdate()->firstOrFail();
            if ($caja->estado !== 'abierta') {
                throw ValidationException::withMessages(['efectivo_contado' => 'Esta caja ya estaba cerrada.']);
            }
            $r = $caja->resumen();
            $caja->update([
                'estado' => 'cerrada', 'cerrada_en' => now(), 'cerrada_por' => $request->user()->id,
                'efectivo_esperado' => $r['esperado'], 'efectivo_contado' => $d['efectivo_contado'],
                'diferencia' => round($d['efectivo_contado'] - $r['esperado'], 2),
                'conteo' => array_filter($d['conteo'] ?? []), 'notas_cierre' => $d['notas_cierre'] ?? null,
            ]);
            return $r;
        });
        Actividad::registrar('pos_caja_cerrar', 'Esperado ' . $cfg->dinero($r['esperado']) . ' · contado ' . $cfg->dinero($d['efectivo_contado']));
        return redirect()->route('pos.cajas.ver', $caja)->with('mensaje', 'Caja cerrada. Este es el reporte del turno.');
    }

    public function historial(Request $request)
    {
        $c = $this->comunes($request);
        return view('pos.cajas', $c + [
            'cajas' => PosCaja::where('cyber_id', $c['cyberPos']->id)->with('usuario', 'cerro')->latest('id')->paginate(25),
        ]);
    }

    /** Reporte de un turno (se puede imprimir en la impresora de tiquetes). */
    public function ver(Request $request, PosCaja $caja)
    {
        $this->delCyber($request, $caja);
        $c = $this->comunes($request);
        return view('pos.caja-reporte', $c + [
            'caja' => $caja->load('usuario', 'cerro'), 'r' => $caja->resumen(),
            'movs' => $caja->movimientos()->with('usuario')->oldest()->get(),
            'imprimir' => $request->boolean('imprimir'),
        ]);
    }
}
