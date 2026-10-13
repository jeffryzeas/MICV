<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Actividad;
use App\Models\User;
use App\Services\Correo;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/** Avisos por correo a los usuarios (por ejemplo, para que completen su CV). */
class AvisoController extends Controller
{
    public const MAXIMO_POR_ENVIO = 100; // Gmail permite unos 500 correos al día por cuenta normal

    public const GRUPOS = [
        'incompletos' => 'Usuarios con el CV incompleto',
        'sin_entrar'  => 'Usuarios que no entran hace más de 30 días',
        'todos'       => 'Todos los usuarios registrados',
        'uno'         => 'Una sola persona (por correo)',
    ];

    public function index(Request $request)
    {
        $grupo = $request->query('grupo', 'incompletos');
        $minimo = (int) $request->query('minimo', 80);
        $destinos = $this->destinatarios($grupo, $minimo, (string) $request->query('correo', ''));
        return view('admin.avisos', [
            'grupo' => $grupo, 'minimo' => $minimo, 'cantidad' => $destinos->count(), 'muestra' => $destinos->take(8),
            'correoListo' => Correo::configurado(), 'correo' => $request->query('correo', ''),
        ]);
    }

    public function enviar(Request $request)
    {
        $d = $request->validate([
            'grupo'   => ['required', 'in:' . implode(',', array_keys(self::GRUPOS))],
            'minimo'  => ['nullable', 'integer', 'between:10,100'],
            'correo'  => ['nullable', 'email'],
            'asunto'  => ['required', 'string', 'max:120'],
            'mensaje' => ['required', 'string', 'max:2000'],
            'boton'   => ['nullable', 'string', 'max:40'],
        ]);
        if (!Correo::configurado()) {
            return back()->withInput()->withErrors(['general' => 'Primero configure el correo en Admin → Correo Gmail.']);
        }
        $destinos = $this->destinatarios($d['grupo'], (int) ($d['minimo'] ?? 80), (string) ($d['correo'] ?? ''))->take(self::MAXIMO_POR_ENVIO);
        if ($destinos->isEmpty()) {
            return back()->withInput()->withErrors(['general' => 'No hay personas para ese grupo.']);
        }
        @set_time_limit(600);
        $ok = 0; $fallos = 0;
        foreach ($destinos as $u) {
            $avance = $u->cliente?->porcentajeCompleto() ?? 0;
            $reemplazar = fn ($t) => strtr($t, ['{nombre}' => $u->cliente?->primer_nombre ?: $u->name, '{avance}' => $avance . '%']);
            try {
                Correo::enviar($u->email, $reemplazar($d['asunto']), 'correos.aviso', [
                    'nombre'   => $u->cliente?->primer_nombre ?: $u->name,
                    'parrafos' => array_values(array_filter(array_map('trim', preg_split("/\n\s*\n/", $reemplazar($d['mensaje']))))),
                    'boton'    => [($d['boton'] ?? '') ?: 'Completar mi CV', route('mi-cv.panel')],
                ]);
                $ok++;
            } catch (\Throwable $e) {
                report($e);
                $fallos++;
            }
        }
        Actividad::registrar('avisos_enviados', self::GRUPOS[$d['grupo']] . " · $ok enviados" . ($fallos ? ", $fallos con error" : ''));
        return back()->with('mensaje', ($ok === 1 ? 'Se envió 1 aviso' : "Se enviaron $ok avisos") . ($fallos ? " y $fallos fallaron (vea Errores del sistema)" : '') . '.');
    }

    /** @return Collection<int, User> */
    protected function destinatarios(string $grupo, int $minimo, string $correo): Collection
    {
        $base = User::where('rol', 'cliente')->where('activo', true)->whereNotNull('email_verified_at')->with('cliente');
        return match ($grupo) {
            'uno'         => $correo !== '' ? $base->where('email', mb_strtolower($correo))->get() : collect(),
            'sin_entrar'  => $base->where(fn ($w) => $w->whereNull('ultimo_acceso')->orWhere('ultimo_acceso', '<', now()->subDays(30)))->get(),
            'todos'       => $base->get(),
            default       => $base->get()->filter(fn ($u) => ($u->cliente?->porcentajeCompleto() ?? 0) < $minimo)->values(),
        };
    }
}
