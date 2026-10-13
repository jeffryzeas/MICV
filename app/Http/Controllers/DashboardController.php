<?php

namespace App\Http\Controllers;

use App\Models\Actividad;
use App\Models\Cliente;
use App\Models\Cyber;
use App\Models\Tarjeta;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $u = $request->user();
        if ($u->esCliente()) {
            return redirect()->route('mi-cv.panel');
        }
        $base = Cliente::query()->visiblesPara($u);
        $inicioMes = now()->startOfMonth();
        $hace6 = now()->startOfMonth()->subMonths(5);

        // Actividad del equipo: el admin ve todo; el cyber, lo de sus propios usuarios.
        $equipo = $u->esAdmin() ? null : User::where('cyber_id', $u->cyber_id)->pluck('id');
        $act = fn () => Actividad::query()->when($equipo, fn ($q) => $q->whereIn('user_id', $equipo));
        $contar = fn (array $acciones) => $act()->whereIn('accion', $acciones)->where('created_at', '>=', $inicioMes)->count();

        // Serie de 6 meses (se agrupa en PHP para funcionar igual en SQLite y MySQL).
        $meses = collect(range(5, 0))->map(fn ($i) => now()->startOfMonth()->subMonths($i));
        $porMes = fn ($fechas) => $meses->map(fn (Carbon $m) => $fechas->filter(fn ($f) => Carbon::parse($f)->isSameMonth($m))->count())->all();
        $serieClientes = $porMes((clone $base)->where('created_at', '>=', $hace6)->pluck('created_at'));
        $serieCv = $porMes($act()->where('accion', 'cv_pdf')->where('created_at', '>=', $hace6)->pluck('created_at'));

        $recientes = (clone $base)->latest()->limit(40)->get();

        return view('dashboard', [
            'totalClientes'   => (clone $base)->count(),
            'clientesActivos' => (clone $base)->where('activo', true)->count(),
            'clientesMes'     => (clone $base)->where('created_at', '>=', $inicioMes)->count(),
            'cvMes'           => $contar(['cv_pdf']),
            'cartasMes'       => $contar(['carta_pdf']),
            'tarjetasMes'     => $contar(['tarjeta_png', 'tarjeta_pdf', 'tarjeta_hoja', 'tarjeta_correo']),
            'correosMes'      => $contar(['cv_correo', 'tarjeta_correo']),
            'cumpleaneros'    => (clone $base)->whereMonth('fecha_nacimiento', now()->month)->whereDay('fecha_nacimiento', now()->day)->limit(6)->get(),
            'meses'           => $meses->map(fn ($m) => ucfirst($m->locale('es')->isoFormat('MMM')))->all(),
            'serieClientes'   => $serieClientes,
            'serieCv'         => $serieCv,
            'ultimosClientes' => $recientes->take(8),
            'porCompletar'    => $recientes->filter(fn ($c) => $c->porcentajeCompleto() < 70)->take(5),
            'conTarjeta'      => Tarjeta::whereIn('cliente_id', $recientes->take(8)->pluck('id'))->pluck('cliente_id')->all(),
            'autoRegistrados' => $u->esAdmin() ? Cliente::whereNotNull('user_id')->count() : 0,
            'cybers'          => $u->esAdmin() ? Cyber::withCount(['clientes', 'usuarios'])->orderBy('nombre')->get() : collect(),
        ]);
    }
}
