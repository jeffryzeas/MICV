<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Actividad;
use App\Models\Cliente;
use App\Models\Configuracion;
use App\Models\RegistroPendiente;
use App\Models\User;
use App\Services\Correo;
use App\Services\Recaptcha;
use App\Services\Verificacion;
use App\Support\Pais;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/** Registro abierto: cualquier persona crea su cuenta para hacer su propio CV. */
class RegistroController extends Controller
{
    public function mostrar()
    {
        abort_unless(Configuracion::actual()->registro_abierto, 404);
        return response()->view('auth.registro')->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
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

        if (\App\Support\AntiBot::esRobot($request)) {
            \App\Models\Actividad::registrar('robot_bloqueado', 'registro · ' . $request->ip());
            return back()->withInput($request->except('password', 'password_confirmation'))->withErrors(['general' => 'No pudimos procesar el formulario. Espere unos segundos e intente de nuevo.']);
        }
        if ($error = Recaptcha::verificar($request->input('recaptcha_token'), 'registro')) {
            return back()->withInput($request->except('password', 'password_confirmation'))->withErrors(['general' => $error]);
        }

        // La cuenta NO se crea todavía: queda en espera hasta que la persona
        // confirme su correo con el código.
        RegistroPendiente::limpiarViejos();
        RegistroPendiente::where('email', $d['email'])->delete();
        $codigo = (string) random_int(100000, 999999);
        $pendiente = RegistroPendiente::create([
            'token'      => Str::random(48),
            'email'      => $d['email'],
            'datos'      => [
                'pais'            => $d['pais'],
                'primer_nombre'   => trim($d['primer_nombre']),
                'primer_apellido' => trim($d['primer_apellido']),
                'password'        => Hash::make($d['password']),
                'terminos'        => Configuracion::actual()->legal_version ?: '1.0',
            ],
            'codigo'     => Hash::make($codigo),
            'expira_en'  => now()->addMinutes(Verificacion::MINUTOS),
            'enviado_en' => now(),
            'ip'         => $request->ip(),
        ]);
        $request->session()->put('registro_token', $pendiente->token);
        Actividad::registrar('registro_iniciado', $d['email']);

        return redirect()->route('registro.codigo')->with($this->enviarCodigo($pendiente, $codigo));
    }

    /** Pantalla para escribir el código (todavía sin cuenta). */
    public function codigoMostrar(Request $request)
    {
        $p = $this->pendiente($request);
        if (!$p) {
            return redirect()->route('registro.mostrar')->withErrors(['general' => 'Su registro venció o no se encontró. Llénelo otra vez, por favor.']);
        }
        $espera = max(0, (int) ceil(Verificacion::ESPERA_REENVIO - $p->enviado_en->diffInSeconds(now())));
        return view('auth.registro-codigo', ['p' => $p, 'espera' => $espera]);
    }

    public function codigoGuardar(Request $request)
    {
        $request->validate(['codigo' => ['required', 'string', 'max:10']]);
        $p = $this->pendiente($request);
        if (!$p) {
            return redirect()->route('registro.mostrar')->withErrors(['general' => 'Su registro venció. Llénelo otra vez, por favor.']);
        }
        if ($p->expira_en->isPast()) {
            return back()->withErrors(['codigo' => 'El código venció. Pida uno nuevo.']);
        }
        if ($p->intentos >= Verificacion::INTENTOS) {
            return back()->withErrors(['codigo' => 'Demasiados intentos con este código. Pida uno nuevo.']);
        }
        if (!Hash::check(preg_replace('/\D/', '', (string) $request->input('codigo')), $p->codigo)) {
            $p->increment('intentos');
            Actividad::registrar('verificacion_fallida', $p->email);
            return back()->withErrors(['codigo' => 'Código incorrecto. Le quedan ' . (Verificacion::INTENTOS - $p->intentos) . ' intento(s).']);
        }
        if (User::where('email', $p->email)->exists()) {
            $p->delete();
            return redirect()->route('login')->withErrors(['general' => 'Ya existe una cuenta con ese correo. Inicie sesión.']);
        }

        $d = $p->datos;
        $pais = Pais::de($d['pais']);
        $user = DB::transaction(function () use ($p, $d, $pais) {
            $user = User::create([
                'usuario'  => $this->usuarioLibre($p->email),
                'name'     => trim($d['primer_nombre'] . ' ' . $d['primer_apellido']),
                'email'    => $p->email,
                'password' => $d['password'], // ya viene con hash
                'rol'      => 'cliente',
                'activo'   => true,
                'terminos_version'      => $d['terminos'],
                'terminos_aceptados_en' => $p->created_at,
            ]);
            $user->forceFill(['email_verified_at' => now()])->save();
            Cliente::create([
                'user_id'              => $user->id,
                'pais'                 => $d['pais'],
                'tipo_documento'       => array_key_first($pais['documentos']),
                'numero_documento'     => null,
                'primer_nombre'        => $d['primer_nombre'],
                'primer_apellido'      => $d['primer_apellido'],
                'nacionalidad'         => $pais['gentilicio'] ?: null,
                'telefono_codigo_pais' => $pais['telefono'] ?: null,
                'correo'               => $p->email,
                'activo'               => true,
                'creado_por'           => $user->id,
            ]);
            $p->delete();
            return $user;
        });

        Auth::login($user);
        $request->session()->forget('registro_token');
        $request->session()->regenerate();
        Actividad::registrar('registro_publico', $user->email);
        try {
            Correo::enviar($user->email, '¡Bienvenido(a) a ' . Configuracion::actual()->nombre_negocio . '!', 'correos.bienvenida', ['nombre' => $d['primer_nombre']]);
        } catch (\Throwable $e) {
            report($e); // la bienvenida es opcional: la cuenta ya está creada
        }

        return redirect()->route('mi-cv.panel')->with('mensaje', '¡Cuenta creada y verificada! Ya puede llenar sus datos y descargar su CV.');
    }

    public function codigoReenviar(Request $request)
    {
        $p = $this->pendiente($request);
        if (!$p) {
            return redirect()->route('registro.mostrar');
        }
        $espera = (int) ceil(Verificacion::ESPERA_REENVIO - $p->enviado_en->diffInSeconds(now()));
        if ($espera > 0) {
            return back()->withErrors(['codigo' => "Espere $espera segundos para pedir otro código."]);
        }
        $codigo = (string) random_int(100000, 999999);
        $p->update(['codigo' => Hash::make($codigo), 'expira_en' => now()->addMinutes(Verificacion::MINUTOS), 'intentos' => 0, 'enviado_en' => now()]);
        return back()->with($this->enviarCodigo($p, $codigo));
    }

    /** "Me equivoqué de correo": borra el registro en espera y vuelve al formulario. */
    public function codigoCancelar(Request $request)
    {
        $this->pendiente($request)?->delete();
        $request->session()->forget('registro_token');
        return redirect()->route('registro.mostrar');
    }

    protected function pendiente(Request $request): ?RegistroPendiente
    {
        $token = (string) $request->session()->get('registro_token', '');
        if ($token === '') {
            return null;
        }
        $p = RegistroPendiente::where('token', $token)->first();
        if ($p && $p->created_at->lt(now()->subHours(RegistroPendiente::HORAS_VIGENCIA))) {
            $p->delete();
            return null;
        }
        return $p;
    }

    protected function enviarCodigo(RegistroPendiente $p, string $codigo): array
    {
        try {
            Correo::enviar($p->email, "Su código de verificación: $codigo", 'correos.codigo', [
                'nombre' => $p->nombre(), 'codigo' => $codigo, 'minutos' => Verificacion::MINUTOS, 'tipo' => 'verificacion',
            ]);
            return ['mensaje' => 'Le enviamos un código de 6 dígitos a su correo. Revise también la carpeta de spam.'];
        } catch (\Throwable $e) {
            return ['aviso_correo' => $e->getMessage(), 'codigo_debug' => (config('app.debug') && app()->environment('local')) ? $codigo : null];
        }
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
