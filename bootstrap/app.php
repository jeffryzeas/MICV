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

        // Va antes que la sesión: si la BD no responde se muestra un aviso claro.
        $middleware->web(prepend: [
            \App\Http\Middleware\RequiereInstalacion::class,
        ]);

        // Sin sesión → al login; con sesión → no mostrar el login.
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('dashboard'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
