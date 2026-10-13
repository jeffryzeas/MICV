<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Actividad;
use Illuminate\Http\Request;

class ActividadController extends Controller
{
    public function index(Request $request)
    {
        $accion = (string) $request->query('accion', '');
        $usuario = trim((string) $request->query('usuario', ''));
        $desde = $request->query('desde');
        $hasta = $request->query('hasta');

        $registros = Actividad::with('usuario.cyber')
            ->when($accion !== '', fn ($q) => $q->where('accion', $accion))
            ->when($usuario !== '', fn ($q) => $q->where(fn ($w) => $w
                ->whereHas('usuario', fn ($u) => $u->where('usuario', 'like', "%$usuario%")->orWhere('name', 'like', "%$usuario%"))
                ->orWhere('detalle', 'like', "%$usuario%")))
            ->when($desde, fn ($q) => $q->whereDate('created_at', '>=', $desde))
            ->when($hasta, fn ($q) => $q->whereDate('created_at', '<=', $hasta))
            ->latest('created_at')->latest('id')->paginate(50)->withQueryString();

        return view('admin.actividad', [
            'registros' => $registros,
            'acciones'  => Actividad::query()->distinct()->orderBy('accion')->pluck('accion'),
            'accion' => $accion, 'usuario' => $usuario, 'desde' => $desde, 'hasta' => $hasta,
            'hoy'       => Actividad::whereDate('created_at', today())->count(),
            'fallidos'  => Actividad::where('accion', 'login_fallido')->where('created_at', '>=', now()->subDay())->count(),
        ]);
    }
}
