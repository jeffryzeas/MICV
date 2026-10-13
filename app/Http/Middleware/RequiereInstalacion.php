<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Mientras no exista ningún usuario, todo lleva a /instalar para crear
 * el primer administrador. Después /instalar queda bloqueado para siempre.
 */
class RequiereInstalacion
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->routeIs('instalar.*') || $request->is('up')) {
            return $next($request);
        }

        try {
            $hayUsuarios = User::query()->exists();
        } catch (\Throwable $e) {
            // Sin BD o sin migrar: mensaje claro en vez de un error 500 críptico.
            return response()->view('errores.sin-bd', ['error' => $e->getMessage()], 503);
        }

        if (!$hayUsuarios) {
            return redirect()->route('instalar.mostrar');
        }

        return $next($request);
    }
}
