<?php

namespace App\Http\Middleware;

use App\Models\Configuracion;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Si se publica una nueva versión de los textos legales, el usuario debe aceptarla. */
class AceptaTerminos
{
    public function handle(Request $request, Closure $next): Response
    {
        $u = $request->user();
        if ($u && !$u->esAdmin() && !$request->routeIs('legal.*', 'logout', 'verificar.*', 'mi-cuenta.*')) {
            $version = Configuracion::actualSegura()?->legal_version ?: '1.0';
            if ($u->terminos_version !== $version) {
                return redirect()->route('legal.aceptar');
            }
        }
        return $next($request);
    }
}
