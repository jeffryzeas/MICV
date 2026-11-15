<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Actividad;
use App\Models\CodigoVerificacion;
use App\Models\Cyber;
use App\Models\User;
use App\Support\EdicionCliente;
use App\Support\Permisos;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class UsuarioController extends Controller
{
    public function index(Request $request)
    {
        $filtros = $request->query();
        $buscar = is_string($filtros['q'] ?? null) ? mb_substr(trim($filtros['q']), 0, 150) : '';
        $cyberId = filter_var($filtros['cyber_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $rol = $filtros['rol'] ?? null;
        $estado = $filtros['estado'] ?? null;
        $verificacion = $filtros['verificacion'] ?? null;

        $cyberId = $cyberId ? (string) $cyberId : '';
        $rol = in_array($rol, array_keys(User::ROLES), true) ? $rol : '';
        $estado = in_array($estado, ['activo', 'inactivo'], true) ? $estado : '';
        $verificacion = in_array($verificacion, ['verificado', 'pendiente'], true) ? $verificacion : '';
        if ($verificacion !== '' && $rol !== '' && $rol !== 'cliente') {
            $verificacion = '';
        }

        $patron = '%'.strtr($buscar, ['!' => '!!', '%' => '!%', '_' => '!_']).'%';

        $usuarios = User::query()
            ->with(['cyber', 'encargado', 'cliente.cybers'])
            ->when($cyberId !== '', fn ($query) => $query->where(
                fn ($cuentas) => $cuentas->where('cyber_id', $cyberId)
                    ->orWhereHas('cliente.cybers', fn ($negocios) => $negocios->where('cybers.id', $cyberId)),
            ))
            ->when($buscar !== '', fn ($query) => $query->where(
                fn ($cuentas) => $cuentas->whereRaw("name LIKE ? ESCAPE '!'", [$patron])
                    ->orWhereRaw("usuario LIKE ? ESCAPE '!'", [$patron])
                    ->orWhereRaw("email LIKE ? ESCAPE '!'", [$patron]),
            ))
            ->when($rol !== '', fn ($query) => $query->where('rol', $rol))
            ->when($estado !== '', fn ($query) => $query->where('activo', $estado === 'activo'))
            ->when($verificacion !== '', fn ($query) => $query
                ->where('rol', 'cliente')
                ->whereNull('email_verified_at', 'and', $verificacion === 'verificado'))
            ->orderByRaw("CASE WHEN rol = 'admin' THEN 0 WHEN rol = 'cyber' THEN 1 ELSE 2 END")
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(20)
            ->appends(array_filter([
                'q' => $buscar, 'cyber_id' => $cyberId, 'rol' => $rol,
                'estado' => $estado, 'verificacion' => $verificacion,
            ], fn ($valor) => $valor !== ''));

        $cybers = Cyber::orderBy('nombre')->get(['id', 'nombre']);
        $hayFiltros = $buscar !== '' || $cyberId !== '' || $rol !== '' || $estado !== '' || $verificacion !== '';

        return view('admin.usuarios.index', compact(
            'usuarios', 'buscar', 'cybers', 'cyberId', 'rol', 'estado', 'verificacion', 'hayFiltros',
        ));
    }

    public function create(Request $request)
    {
        $cybers = Cyber::orderBy("nombre")->get();
        $negocio = filter_var($request->query('cyber_id'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        return view("admin.usuarios.form", [
            "usuario" => new User([
                "rol" => "cyber",
                "activo" => true,
                "cyber_id" => $negocio ?: null,
            ]),
            "cybers" => $cybers,
            "encargados" => User::where("rol", "cyber")
                ->whereNull("encargado_id")
                ->where("activo", true)
                ->orderBy("name")
                ->get(),
        ]);
    }

    public function store(Request $request)
    {
        $datos = $this->validar($request);
        $datos["password"] = $request->input("password");

        $u = User::create($datos);
        Actividad::registrar("usuario_crear", "{$u->usuario} ({$u->rol})");

        return redirect()
            ->route("admin.usuarios.index")
            ->with(
                "mensaje",
                "Usuario {$u->usuario} creado como {$u->rolLegible()}.",
            );
    }

    public function edit(User $usuario)
    {
        $cybers = Cyber::orderBy("nombre")->get();

        return view("admin.usuarios.form", [
            "usuario" => $usuario,
            "cybers" => $cybers,
            "encargados" => User::where("rol", "cyber")
                ->whereNull("encargado_id")
                ->where("activo", true)
                ->where("id", "!=", $usuario->id)
                ->orderBy("name")
                ->get(),
        ]);
    }

    public function update(Request $request, User $usuario)
    {
        DB::transaction(function () use ($request, $usuario) {
            $cuenta = User::lockForUpdate()->findOrFail($usuario->id);
            $datos = $this->validar($request, $cuenta);

            if ($cuenta->is($request->user()) && !$datos['activo']) {
                throw ValidationException::withMessages(['activo' => 'No puede desactivarse a sí mismo.']);
            }
            if ($this->dejariaSinAdmins($cuenta, $datos['rol'], $datos['activo'])) {
                throw ValidationException::withMessages(['rol' => 'Debe quedar al menos un administrador activo.']);
            }

            if ($request->filled('password')) {
                $datos['password'] = $request->input('password');
            }
            $cuenta->fill($datos);
            $cambioCorreo = $cuenta->isDirty('email');
            if ($cambioCorreo) {
                $cuenta->email_verified_at = null;
                $cuenta->cliente?->update(['correo' => $cuenta->email]);
            }
            $cuenta->save();

            if ($cambioCorreo || isset($datos['password'])) {
                CodigoVerificacion::where('user_id', $cuenta->id)->whereNull('usado_en')->delete();
                DB::table('jz_invitaciones')->where('user_id', $cuenta->id)->delete();
            }

            Actividad::registrar('usuario_editar', $cuenta->usuario);
        }, 3);

        return redirect()->route('admin.usuarios.index')->with('mensaje', 'Usuario actualizado.');
    }

    public function toggle(Request $request, User $usuario)
    {
        $datos = $request->validate(['activo' => ['sometimes', 'required', 'boolean']]);
        $activo = DB::transaction(function () use ($request, $usuario, $datos) {
            $cuenta = User::lockForUpdate()->findOrFail($usuario->id);
            if ($cuenta->is($request->user())) {
                throw ValidationException::withMessages(['general' => 'No puede desactivarse a sí mismo.']);
            }

            $activo = isset($datos['activo']) ? (bool) $datos['activo'] : !$cuenta->activo;
            if ($activo === $cuenta->activo) {
                return $activo;
            }
            if ($this->dejariaSinAdmins($cuenta, $cuenta->rol, $activo)) {
                throw ValidationException::withMessages(['general' => 'Debe quedar al menos un administrador activo.']);
            }

            $cuenta->update(['activo' => $activo]);
            Actividad::registrar('usuario_toggle', $cuenta->usuario.' → '.($activo ? 'activo' : 'inactivo'));

            return $activo;
        }, 3);

        return back()->with('mensaje', $activo ? 'Usuario activado.' : 'Usuario desactivado.');
    }

    public function destroy(Request $request, User $usuario)
    {
        if ($usuario->is($request->user())) {
            return back()->withErrors(['general' => 'No puede eliminarse a sí mismo.']);
        }

        DB::transaction(function () use ($usuario) {
            $cuenta = User::lockForUpdate()->findOrFail($usuario->id);
            if ($this->dejariaSinAdmins($cuenta, 'cyber', false)) {
                throw ValidationException::withMessages(['general' => 'Debe quedar al menos un administrador activo.']);
            }
            if ($cuenta->cajeros()->exists()) {
                throw ValidationException::withMessages(['general' => 'Reasigne primero los cajeros de este encargado.']);
            }

            $cuenta->delete();
            Actividad::registrar('usuario_eliminar', $cuenta->usuario);
        }, 3);

        return redirect()->route('admin.usuarios.index')->with('mensaje', 'Usuario eliminado.');
    }

    public function verificar(Request $request, User $usuario)
    {
        $datos = $request->validate(['email' => ['sometimes', 'required', 'string', 'max:150']]);
        $mensaje = DB::transaction(function () use ($usuario, $datos) {
            $cuenta = User::lockForUpdate()->findOrFail($usuario->id);
            abort_unless($cuenta->esCliente(), 403);

            if (isset($datos['email']) && $datos['email'] !== $cuenta->email) {
                throw ValidationException::withMessages(['general' => 'El correo cambió. Actualice la lista y vuelva a confirmar.']);
            }
            if ($cuenta->verificado()) {
                return 'El correo ya estaba verificado.';
            }

            $cuenta->forceFill(['email_verified_at' => now()])->save();
            CodigoVerificacion::where('user_id', $cuenta->id)
                ->where('tipo', 'verificacion')
                ->whereNull('usado_en')
                ->delete();
            Actividad::registrar('usuario_verificar_manual', $cuenta->email);

            return "Correo de {$cuenta->usuario} marcado como verificado.";
        }, 3);

        return back()->with('mensaje', $mensaje);
    }

    protected function validar(Request $request, ?User $u = null): array
    {
        $request->merge([
            'usuario' => is_string($request->input('usuario')) ? mb_strtoupper($request->input('usuario')) : $request->input('usuario'),
            'email' => is_string($request->input('email')) ? mb_strtolower($request->input('email')) : $request->input('email'),
        ]);

        $datos = $request->validate(
            [
                "name" => ["required", "string", "max:150"],
                "usuario" => [
                    "required",
                    "string",
                    "min:3",
                    "max:40",
                    'regex:/^[A-Z0-9._-]+$/',
                    Rule::unique("users", "usuario")->ignore($u?->id),
                ],
                "email" => [
                    "required",
                    "email",
                    "max:150",
                    Rule::unique("users", "email")->ignore($u?->id),
                ],
                "rol" => [
                    "required",
                    Rule::in(
                        $u?->esCliente() ? ["cliente"] : ["admin", "cyber"],
                    ),
                ],
                "cyber_id" => [
                    "nullable",
                    "required_if:rol,cyber",
                    "integer",
                    "exists:cybers,id",
                ],
                "activo" => ["sometimes", "required", "boolean"],
                "perfil_acceso" => ["nullable", "in:encargado,cajero"],
                "encargado_id" => ["nullable", "integer"],
                "permisos" => ["nullable", "array", "max:80"],
                "permisos.*" => [
                    "string",
                    Rule::in(array_keys(Permisos::LISTA)),
                ],
                "password" => [
                    $u ? "nullable" : "required",
                    "confirmed",
                    "max:200",
                    Password::min(8),
                ],
            ],
            [
                "usuario.regex" =>
                    "El usuario solo puede tener letras, números, punto, guion y guion bajo (sin espacios).",
                "cyber_id.required_if" =>
                    "Un usuario de negocio debe tener un negocio asignado.",
            ],
            [
                "name" => "nombre completo",
                "password" => "contraseña",
                "cyber_id" => "negocio",
                "encargado_id" => "encargado",
                "email" => "correo electrónico",
            ],
        );
        if ($datos["rol"] !== "cyber") {
            $datos["cyber_id"] = null;
        }

        $perfil =
            $datos["perfil_acceso"] ??
            ($u?->encargado_id ? "cajero" : "encargado");
        if (
            $u &&
            $u->cajeros()->exists() &&
            ($datos["rol"] !== "cyber" ||
                (int) ($datos["cyber_id"] ?? 0) !== (int) $u->cyber_id ||
                $perfil === "cajero")
        ) {
            throw ValidationException::withMessages([
                "cyber_id" => "Reasigne primero los cajeros de este encargado.",
            ]);
        }
        if ($datos["rol"] === "cyber" && $perfil === "cajero") {
            $enc = User::where("rol", "cyber")
                ->whereNull("encargado_id")
                ->where("activo", true)
                ->where("cyber_id", $datos["cyber_id"])
                ->where("id", "!=", $u?->id ?? 0)
                ->find($datos["encargado_id"] ?? ($u?->encargado_id ?? 0));
            if (!$enc) {
                throw ValidationException::withMessages([
                    "encargado_id" =>
                        "Seleccione un encargado activo del mismo negocio.",
                ]);
            }
            $datos["encargado_id"] = $enc->id;
        } else {
            $datos["encargado_id"] = null;
        }
        if ($datos["rol"] === "cyber") {
            $lista = $request->has("permisos_enviados")
                ? $datos["permisos"] ?? []
                : $u?->permisos ??
                    Permisos::perfil(
                        $datos["encargado_id"] ? "cajero" : "encargado",
                    );
            if ($datos["encargado_id"]) {
                $lista = array_values(array_diff($lista, ["personal"]));
            }
            $datos["permisos"] = Permisos::completar($lista);
        } else {
            $datos["permisos"] = null;
        }
        if ($datos['rol'] === 'cliente' && $request->boolean('edicion_cliente_enviada')) {
            $edicion = $request->validate([
                'edicion_cliente' => ['nullable', 'array', 'max:4'],
                'edicion_cliente.*' => ['string', Rule::in(array_keys(EdicionCliente::GRUPOS))],
            ]);
            $datos['edicion_cliente'] = array_values(array_unique($edicion['edicion_cliente'] ?? []));
        } elseif ($datos['rol'] !== 'cliente') {
            $datos['edicion_cliente'] = null;
        }

        unset($datos["perfil_acceso"]);
        unset($datos["password"]);
        $datos["activo"] = $request->boolean("activo");

        return $datos;
    }

    /** ¿El cambio dejaría el sistema sin ningún admin activo? */
    protected function dejariaSinAdmins(
        User $u,
        string $nuevoRol,
        bool $nuevoActivo,
    ): bool {
        if ($u->rol !== "admin" || !$u->activo) {
            return false;
        }
        if ($nuevoRol === "admin" && $nuevoActivo) {
            return false;
        }

        return User::where("rol", "admin")
            ->where("activo", true)
            ->where("id", "!=", $u->id)
            ->orderBy("id")
            ->lockForUpdate()
            ->first(["id"]) === null;
    }
}
