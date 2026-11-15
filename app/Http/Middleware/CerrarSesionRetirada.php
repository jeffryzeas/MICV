<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CerrarSesionRetirada
{
    public function handle(Request $request, Closure $next): Response
    {
        // Las sesiones emitidas por la antigua API no deben adquirir los permisos de una sesión web.
        if ($request->session()->has('desktop_device_id')) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return $request->expectsJson()
                ? response()->json(['message' => 'Inicie sesión en la web para continuar.'], 401)
                : redirect()->route('login');
        }

        return $next($request);
    }
}
