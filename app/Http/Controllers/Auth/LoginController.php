<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Actividad;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class LoginController extends Controller
{
    public function mostrar()
    {
        // Sin caché: así el botón "Atrás" no muestra un login con un token vencido.
        return response()->view('auth.login')->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
    }

    public function iniciar(Request $request)
    {
        $datos = $request->validate([
            'usuario'  => ['required', 'string', 'max:150'],
            'password' => ['required', 'string', 'max:200'],
        ], [], ['usuario' => 'usuario o correo', 'password' => 'contraseña']);

        if ($error = \App\Services\Recaptcha::verificar($request->input('recaptcha_token'), 'login')) {
            return back()->withInput($request->only('usuario'))->withErrors(['usuario' => $error]);
        }

        $login = trim($datos['usuario']);
        $campo = str_contains($login, '@') ? 'email' : 'usuario';
        $valor = $campo === 'email' ? mb_strtolower($login) : mb_strtoupper($login);

        $user = User::where($campo, $valor)->first();

        // Se compara siempre una contraseña (aunque el usuario no exista) para que el
        // tiempo de respuesta no revele qué usuarios existen.
        $ok = Hash::check($datos['password'], $user?->password ?? '$2y$12$WuZFIKIqG08IyL7wS8ghleu3RnuXKjBxAUrwmJhLCc8icgb4GG04G');
        if (!$user || !$ok) {
            Actividad::registrar('login_fallido', mb_substr($login, 0, 80));
            return back()->withInput($request->only('usuario'))
                ->withErrors(['usuario' => 'Usuario o contraseña incorrectos.']);
        }

        if (!$user->activo) {
            return back()->withInput($request->only('usuario'))
                ->withErrors(['usuario' => 'Este usuario está desactivado. Hable con el administrador.']);
        }

        if ($user->rol === 'cyber' && (!$user->cyber || !$user->cyber->activo)) {
            return back()->withInput($request->only('usuario'))
                ->withErrors(['usuario' => $user->cyber ? 'Su Cyber está en mantenimiento. Hable con el administrador.' : 'Su usuario no tiene un Cyber asignado.']);
        }

        Auth::login($user, $request->boolean('recordar'));
        $request->session()->regenerate();
        $user->forceFill(['ultimo_acceso' => now()])->save();
        Actividad::registrar('login');

        if ($user->esCliente()) {
            return redirect()->route($user->verificado() ? 'mi-cv.panel' : 'verificar.mostrar');
        }

        return redirect()->intended(route('dashboard'));
    }

    public function salir(Request $request)
    {
        Actividad::registrar('logout');
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
