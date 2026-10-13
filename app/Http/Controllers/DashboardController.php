<?php

namespace App\Http\Controllers;

use App\Models\Actividad;
use App\Models\Cliente;
use App\Models\Cyber;
use App\Models\User;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $u = $request->user();
        if ($u->esCliente()) {
            return redirect()->route('mi-cv.panel');
        }
        $base = Cliente::query()->visiblesPara($u);

        $cvMes = Actividad::where('accion', 'cv_pdf')->where('created_at', '>=', now()->startOfMonth())
            ->when(!$u->esAdmin(), fn ($q) => $q->whereIn('user_id', User::where('cyber_id', $u->cyber_id)->pluck('id')))
            ->count();

        return view('dashboard', [
            'totalClientes'   => (clone $base)->count(),
            'clientesActivos' => (clone $base)->where('activo', true)->count(),
            'clientesMes'     => (clone $base)->where('created_at', '>=', now()->startOfMonth())->count(),
            'cumpleHoy'       => (clone $base)->whereMonth('fecha_nacimiento', now()->month)->whereDay('fecha_nacimiento', now()->day)->count(),
            'cvMes'           => $cvMes,
            'ultimosClientes' => (clone $base)->latest()->limit(8)->get(),
            'autoRegistrados' => $u->esAdmin() ? Cliente::whereNotNull('user_id')->count() : 0,
            'cybers'          => $u->esAdmin() ? Cyber::withCount(['clientes', 'usuarios'])->orderBy('nombre')->get() : collect(),
        ]);
    }
}
