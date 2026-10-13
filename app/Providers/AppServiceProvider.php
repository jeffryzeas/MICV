<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    /**
     * Límites de intentos contra robots y ataques (fuerza bruta, envío masivo
     * de correos, enumeración de documentos). Cuando se pasa un límite, la
     * persona ve la página 429 en español y puede intentar de nuevo luego.
     */
    public function boot(): void
    {
        $ip = fn (Request $r) => $r->ip();
        $quien = fn (Request $r) => $r->user()?->id ? 'u' . $r->user()->id : 'ip' . $r->ip();
        $correo = fn (Request $r) => mb_strtolower(trim((string) $r->input('email')));

        // Tope general para cualquier página (frena a quien bombardea el sitio).
        RateLimiter::for('chat-leer',fn(Request $r)=>Limit::perMinute(180)->by('chat-leer:'.$r->route()?->getName().':'.$quien($r)));
        RateLimiter::for('chat-escribir',fn(Request $r)=>Limit::perMinute(30)->by('chat-escribir:'.$r->route()?->getName().':'.$quien($r)));
        RateLimiter::for('chat-perfil',fn(Request $r)=>Limit::perMinute(20)->by('chat-perfil:'.$quien($r)));
        RateLimiter::for('global', fn (Request $r) => Limit::perMinute($r->routeIs('chat.*')?600:240)->by(($r->routeIs('chat.*')?'global-chat:':'global:').$quien($r)));

        // Inicio de sesión: por usuario+IP (adivinar contraseñas) y por IP (probar muchos usuarios).
        RateLimiter::for('login', fn (Request $r) => [
            Limit::perMinute(5)->by('login:' . mb_strtolower((string) $r->input('usuario')) . '|' . $r->ip()),
            Limit::perHour(30)->by('login-ip:' . $ip($r)),
        ]);

        // Registro: pocas cuentas por IP.
        RateLimiter::for('registro', fn (Request $r) => [
            Limit::perMinute(3)->by('reg:' . $ip($r)),
            Limit::perDay(20)->by('reg-dia:' . $ip($r)),
        ]);

        // Escribir códigos de 6 dígitos (además del máximo de 5 intentos por código).
        RateLimiter::for('codigo', fn (Request $r) => [
            Limit::perMinute(8)->by('cod:' . $quien($r)),
            Limit::perHour(40)->by('cod-ip:' . $ip($r)),
        ]);

        // Pedir códigos por correo: evita llenar el buzón de alguien.
        RateLimiter::for('envio-codigo', fn (Request $r) => [
            Limit::perMinute(2)->by('env:' . $quien($r)),
            Limit::perHour(8)->by('env-ip:' . $ip($r)),
        ]);
        RateLimiter::for('recuperar', fn (Request $r) => [
            Limit::perMinute(3)->by('rec-ip:' . $ip($r)),
            Limit::perHour(5)->by('rec-correo:' . $correo($r)),
            Limit::perDay(30)->by('rec-ip-dia:' . $ip($r)),
        ]);

        // Formulario público de eliminación de cuenta.
        RateLimiter::for('eliminar-publico', fn (Request $r) => [
            Limit::perHour(5)->by('elim-ip:' . $ip($r)),
            Limit::perDay(3)->by('elim-correo:' . $correo($r)),
        ]);

        // Consultar documentos (evita recorrer números para averiguar quién está registrado).
        RateLimiter::for('documento', fn (Request $r) => [
            Limit::perMinute(40)->by('doc:' . $quien($r)),
            Limit::perDay(600)->by('doc-dia:' . $quien($r)),
        ]);

        // Trabajo pesado: PDF con Chromium y correos con adjunto.
        RateLimiter::for('pdf', fn (Request $r) => [
            Limit::perMinute(20)->by('pdf:' . $quien($r)),
            Limit::perDay(400)->by('pdf-dia:' . $quien($r)),
        ]);
        RateLimiter::for('cv-correo', fn (Request $r) => [
            Limit::perMinutes(10, 5)->by('cvc:' . $quien($r)),
            Limit::perDay(25)->by('cvc-dia:' . $quien($r)),
        ]);

        // Guardar formularios y vistas previas.
        RateLimiter::for('guardar', fn (Request $r) => Limit::perMinute(30)->by('guardar:' . $quien($r)));
        RateLimiter::for('vista', fn (Request $r) => Limit::perMinute(120)->by('vista:' . $quien($r)));
        // Autoservicio público: por IP (varias personas pueden usar el mismo wifi del Cyber).
        RateLimiter::for('autoservicio', fn (Request $r) => [
            Limit::perMinute(6)->by('auto:' . $r->ip()),
            Limit::perDay(60)->by('auto-dia:' . $r->ip()),
        ]);
        // Quitar el fondo usa bastante procesador: límite propio.
        RateLimiter::for('fondo', fn (Request $r) => [
            Limit::perMinute(10)->by('fondo:' . $quien($r)),
            Limit::perDay(300)->by('fondo-dia:' . $quien($r)),
        ]);
        RateLimiter::for('sensible', fn (Request $r) => [
            Limit::perMinute(5)->by('sens:' . $quien($r)),
            Limit::perDay(30)->by('sens-dia:' . $quien($r)),
        ]);
        RateLimiter::for('token', fn (Request $r) => Limit::perMinute(20)->by('tok:' . $ip($r)));
    }
}
