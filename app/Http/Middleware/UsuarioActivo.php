<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/** Si un admin desactiva a un usuario, su sesión se cierra en la siguiente petición. */
class UsuarioActivo
{
    public function handle(Request $request, Closure $next): Response
    {
        $u = $request->user();
        if ($u && (!$u->activo || ($u->rol === 'cyber' && (!$u->cyber || !$u->cyber->activo)))) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            return redirect()->route('login')->withErrors(['usuario' => 'Su acceso fue desactivado (usuario o negocio en mantenimiento). Hable con el administrador.']);
        }
        return $next($request);
    }
}
