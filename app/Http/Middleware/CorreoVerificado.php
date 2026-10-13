<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Los clientes que se registraron solos deben verificar su correo con el código. */
class CorreoVerificado
{
    public function handle(Request $request, Closure $next): Response
    {
        $u = $request->user();
        if ($u && $u->esCliente() && !$u->verificado() && !$request->routeIs('verificar.*', 'logout')) {
            return redirect()->route('verificar.mostrar');
        }
        return $next($request);
    }
}
