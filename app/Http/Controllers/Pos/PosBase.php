<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Models\Cyber;
use App\Models\Pos\PosConfig;
use Illuminate\Http\Request;

/**
 * Base del punto de venta: cada Cyber tiene su propio inventario, caja y ventas.
 * El usuario de un Cyber trabaja en el suyo; el admin elige en cuál (se recuerda en la sesión).
 */
abstract class PosBase extends Controller
{
    protected function cyber(Request $request): Cyber
    {
        $u = $request->user();
        if (!$u->esAdmin()) {
            abort_unless($u->cyber, 403, 'Su usuario no tiene un Cyber asignado.');
            return $u->cyber;
        }
        if ($request->filled('cyber')) {
            $request->session()->put('pos_cyber', $request->integer('cyber'));
        }
        $cy = Cyber::find($request->session()->get('pos_cyber')) ?? Cyber::orderBy('nombre')->first();
        if (!$cy) {
            abort(response()->view('pos.sin-cyber'));
        }
        return $cy;
    }

    protected function config(Request $request): PosConfig
    {
        return PosConfig::para($this->cyber($request));
    }

    /** Datos comunes para las vistas (selector de Cyber del admin, config). */
    protected function comunes(Request $request): array
    {
        $cy = $this->cyber($request);
        return [
            'cyberPos' => $cy,
            'cfg' => PosConfig::para($cy),
            'cybersPos' => $request->user()->esAdmin() ? Cyber::orderBy('nombre')->get(['id', 'nombre']) : collect(),
        ];
    }

    /** Impide tocar registros de otro Cyber. */
    protected function delCyber(Request $request, $modelo): void
    {
        abort_unless($modelo && (int) $modelo->cyber_id === $this->cyber($request)->id, 404);
    }
}
