<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Uso: ->middleware('rol:admin')  o  ->middleware('rol:admin,cyber') */
class VerificarRol
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $u = $request->user();
        if (!$u || !in_array($u->rol, $roles, true)) {
            abort(403, 'No tiene permiso para entrar a esta sección.');
        }
        return $next($request);
    }
}
