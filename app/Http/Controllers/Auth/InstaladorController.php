<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Actividad;
use App\Models\Configuracion;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;

/**
 * Pantalla de primera vez: crea el primer administrador.
 * Solo funciona mientras la tabla users esté vacía.
 */
class InstaladorController extends Controller
{
    public function mostrar()
    {
        if ($this->yaInstalado()) {
            return redirect()->route('login');
        }
        return view('auth.instalar');
    }

    public function guardar(Request $request)
    {
        if ($this->yaInstalado()) {
            return redirect()->route('login');
        }

        $datos = $request->validate([
            'nombre_negocio' => ['required', 'string', 'max:120'],
            'name'           => ['required', 'string', 'max:150'],
            'usuario'        => ['required', 'string', 'min:3', 'max:40', 'regex:/^[A-Za-z0-9._-]+$/'],
            'email'          => ['required', 'email', 'max:150'],
            'password'       => ['required', 'confirmed', Password::min(8)],
        ], [
            'usuario.regex' => 'El usuario solo puede tener letras, números, punto, guion y guion bajo.',
        ], [
            'nombre_negocio' => 'nombre del negocio', 'name' => 'nombre completo', 'password' => 'contraseña',
        ]);

        $user = User::create([
            'usuario'  => mb_strtoupper($datos['usuario']),
            'name'     => $datos['name'],
            'email'    => mb_strtolower($datos['email']),
            'password' => $datos['password'],
            'rol'      => 'admin',
            'activo'   => true,
        ]);

        $cfg = Configuracion::actual();
        $cfg->update(['nombre_negocio' => $datos['nombre_negocio']]);

        Auth::login($user);
        $request->session()->regenerate();
        Actividad::registrar('instalacion', 'Primer administrador creado');

        return redirect()->route('dashboard')->with('mensaje', '¡Listo! El sistema quedó instalado y usted es el administrador.');
    }

    protected function yaInstalado(): bool
    {
        try {
            return User::query()->exists();
        } catch (\Throwable $e) {
            abort(503, 'No hay conexión con la base de datos. Revise el .env y ejecute: php artisan migrate');
        }
    }
}
