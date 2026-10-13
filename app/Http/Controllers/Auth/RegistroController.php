<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Actividad;
use App\Models\Cliente;
use App\Models\Configuracion;
use App\Models\User;
use App\Services\Recaptcha;
use App\Services\Verificacion;
use App\Support\Pais;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/** Registro abierto: cualquier persona crea su cuenta para hacer su propio CV. */
class RegistroController extends Controller
{
    public function mostrar()
    {
        abort_unless(Configuracion::actual()->registro_abierto, 404);
        return view('auth.registro');
    }

    public function guardar(Request $request)
    {
        abort_unless(Configuracion::actual()->registro_abierto, 404);

        $request->merge(['email' => mb_strtolower(trim((string) $request->input('email')))]);
        $d = $request->validate([
            'pais'            => ['required', Rule::in(Pais::codigos())],
            'primer_nombre'   => ['required', 'string', 'max:60'],
            'primer_apellido' => ['required', 'string', 'max:60'],
            'email'           => ['required', 'email', 'max:150', Rule::unique('users', 'email')],
            'password'        => ['required', 'confirmed', Password::min(8)],
            'acepto'          => ['accepted'],
        ], [
            'email.unique'    => 'Ya existe una cuenta con ese correo. Inicie sesión.',
            'acepto.accepted' => 'Debe aceptar los Términos y condiciones y la Política de privacidad.',
        ], ['pais' => 'nacionalidad', 'primer_nombre' => 'nombre', 'primer_apellido' => 'apellido', 'email' => 'correo', 'password' => 'contraseña']);

        if ($error = Recaptcha::verificar($request->input('recaptcha_token'), 'registro')) {
            return back()->withInput($request->except('password', 'password_confirmation'))->withErrors(['general' => $error]);
        }

        $pais = Pais::de($d['pais']);
        $user = DB::transaction(function () use ($d, $pais) {
            $user = User::create([
                'usuario'  => $this->usuarioLibre($d['email']),
                'name'     => trim($d['primer_nombre'] . ' ' . $d['primer_apellido']),
                'email'    => $d['email'],
                'password' => $d['password'],
                'rol'      => 'cliente',
                'activo'   => true,
                'terminos_version'      => Configuracion::actual()->legal_version ?: '1.0',
                'terminos_aceptados_en' => now(),
            ]);
            Cliente::create([
                'user_id'              => $user->id,
                'pais'                 => $d['pais'],
                'tipo_documento'       => array_key_first($pais['documentos']),
                'numero_documento'     => null,
                'primer_nombre'        => $d['primer_nombre'],
                'primer_apellido'      => $d['primer_apellido'],
                'nacionalidad'         => $pais['gentilicio'] ?: null,
                'telefono_codigo_pais' => $pais['telefono'] ?: null,
                'correo'               => $d['email'],
                'activo'               => true,
                'creado_por'           => $user->id,
            ]);
            return $user;
        });

        Auth::login($user);
        $request->session()->regenerate();
        Actividad::registrar('registro_publico', $user->email);

        $r = Verificacion::enviar($user);
        return redirect()->route('verificar.mostrar')->with($this->flashEnvio($r));
    }

    public function verificarMostrar(Request $request)
    {
        $u = $request->user();
        if (!$u->esCliente() || $u->verificado()) {
            return redirect()->route('dashboard');
        }
        return view('auth.verificar', ['user' => $u, 'espera' => Verificacion::puedeReenviar($u)]);
    }

    public function verificarGuardar(Request $request)
    {
        $request->validate(['codigo' => ['required', 'string', 'max:10']]);
        $u = $request->user();

        if ($error = Verificacion::comprobar($u, (string) $request->input('codigo'))) {
            Actividad::registrar('verificacion_fallida', $u->email);
            return back()->withErrors(['codigo' => $error]);
        }

        Actividad::registrar('correo_verificado', $u->email);
        return redirect()->route('mi-cv.panel')->with('mensaje', '¡Cuenta verificada! Ya puede completar sus datos y descargar su CV.');
    }

    public function reenviar(Request $request)
    {
        $u = $request->user();
        if ($u->verificado()) {
            return redirect()->route('dashboard');
        }
        if ($falta = Verificacion::puedeReenviar($u)) {
            return back()->withErrors(['codigo' => "Espere $falta segundos para pedir otro código."]);
        }
        $r = Verificacion::enviar($u);
        return back()->with($this->flashEnvio($r));
    }

    protected function flashEnvio(array $r): array
    {
        if ($r['enviado']) {
            return ['mensaje' => 'Le enviamos un código de 6 dígitos a su correo. Revise también la carpeta de spam.'];
        }
        return ['aviso_correo' => $r['error'], 'codigo_debug' => $r['codigo_debug']];
    }

    protected function usuarioLibre(string $email): string
    {
        $base = Str::upper(Str::limit(preg_replace('/[^A-Za-z0-9]/', '', Str::before($email, '@')) ?: 'CLIENTE', 24, ''));
        $u = $base;
        while (User::where('usuario', $u)->exists()) {
            $u = $base . random_int(10, 9999);
        }
        return $u;
    }
}
