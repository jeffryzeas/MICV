<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'rol'    => \App\Http\Middleware\VerificarRol::class,
            'activo' => \App\Http\Middleware\UsuarioActivo::class,
            'verificado' => \App\Http\Middleware\CorreoVerificado::class,
            'terminos' => \App\Http\Middleware\AceptaTerminos::class,
        ]);

        // Detrás de Cloudflare / un balanceador: TRUSTED_PROXIES=* en .env para ver la IP real.
        $middleware->replace(\Illuminate\Http\Middleware\TrustProxies::class, \App\Http\Middleware\ProxiesConfiables::class);

        // Va antes que la sesión: si la BD no responde se muestra un aviso claro.
        $middleware->web(prepend: [
            \App\Http\Middleware\RequiereInstalacion::class,
        ], append: [
            \App\Http\Middleware\CabecerasSeguridad::class,
            \App\Http\Middleware\PermisosModulo::class,
            \Illuminate\Routing\Middleware\ThrottleRequests::class . ':global',
        ]);

        // Sin sesión → al login; con sesión → no mostrar el login.
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('dashboard'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // "Página expirada" (419): la página estuvo abierta mucho tiempo o se volvió
        // con el botón Atrás. En vez de la pantalla de error, se vuelve al formulario
        // con un aviso amable y un token nuevo, sin perder lo escrito.
        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\HttpException $e, \Illuminate\Http\Request $request) {
            if ($e->getStatusCode() !== 419) {
                return null;
            }
            $aviso = 'La página estuvo abierta mucho tiempo y se venció por seguridad. Ya la actualizamos: intente de nuevo.';
            if ($request->expectsJson()) {
                return response()->json(['message' => $aviso, 'token' => csrf_token()], 419);
            }
            if ($request->routeIs('logout')) {
                \Illuminate\Support\Facades\Auth::logout();
                return redirect()->route('login');
            }
            return redirect()->back(fallback: url('/'))
                ->withInput($request->except(['password', 'password_confirmation', 'actual', '_token', 'foto']))
                ->withErrors(['general' => $aviso]);
        });
    })->create();
