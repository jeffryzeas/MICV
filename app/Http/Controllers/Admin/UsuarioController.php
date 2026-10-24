<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Actividad;
use App\Models\Cyber;
use App\Models\User;
use App\Support\Permisos;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class UsuarioController extends Controller
{
    public function index(Request $request)
    {
        $buscar = trim((string) $request->query("q"));
        $cyberId = (string) $request->query("cyber_id", "");
        $usuarios = User::query()
            ->with(["cyber", "encargado", "cliente.cybers"])
            ->when(
                $cyberId !== "",
                fn($q) => $q->where(
                    fn($w) => $w
                        ->where("cyber_id", $cyberId)
                        ->orWhereHas(
                            "cliente.cybers",
                            fn($c) => $c->where("cybers.id", $cyberId),
                        ),
                ),
            )
            ->when(
                $buscar !== "",
                fn($q) => $q->where(
                    fn($w) => $w
                        ->where("name", "like", "%$buscar%")
                        ->orWhere("usuario", "like", "%$buscar%")
                        ->orWhere("email", "like", "%$buscar%"),
                ),
            )
            ->when($request->query("rol"), fn($q, $r) => $q->where("rol", $r))
            ->orderByRaw(
                "CASE WHEN rol = 'admin' THEN 0 WHEN rol = 'cyber' THEN 1 ELSE 2 END",
            )
            ->orderBy("name")
            ->paginate(20)
            ->withQueryString();

        $cybers = Cyber::orderBy("nombre")->get();

        return view(
            "admin.usuarios.index",
            compact("usuarios", "buscar", "cybers", "cyberId"),
        );
    }

    public function create(Request $request)
    {
        $cybers = Cyber::orderBy("nombre")->get();

        return view("admin.usuarios.form", [
            "usuario" => new User([
                "rol" => "cyber",
                "activo" => true,
                "cyber_id" => $request->query("cyber_id"),
            ]),
            "cybers" => $cybers,
            "encargados" => User::where("rol", "cyber")
                ->whereNull("encargado_id")
                ->where("activo", true)
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
                ->get(),
        ]);
    }

    public function update(Request $request, User $usuario)
    {
        $datos = $this->validar($request, $usuario);

        if (
            $this->dejariaSinAdmins(
                $usuario,
                $datos["rol"],
                (bool) $datos["activo"],
            )
        ) {
            return back()
                ->withInput()
                ->withErrors([
                    "rol" => "Debe quedar al menos un administrador activo.",
                ]);
        }
        if ($usuario->is($request->user()) && !$datos["activo"]) {
            return back()
                ->withInput()
                ->withErrors(["activo" => "No puede desactivarse a sí mismo."]);
        }

        if ($request->filled("password")) {
            $datos["password"] = $request->input("password");
        }

        $usuario->update($datos);
        Actividad::registrar("usuario_editar", $usuario->usuario);

        return redirect()
            ->route("admin.usuarios.index")
            ->with("mensaje", "Usuario actualizado.");
    }

    public function toggle(Request $request, User $usuario)
    {
        if ($usuario->is($request->user())) {
            return back()->withErrors([
                "general" => "No puede desactivarse a sí mismo.",
            ]);
        }
        if (
            $this->dejariaSinAdmins($usuario, $usuario->rol, !$usuario->activo)
        ) {
            return back()->withErrors([
                "general" => "Debe quedar al menos un administrador activo.",
            ]);
        }

        $usuario->update(["activo" => !$usuario->activo]);
        Actividad::registrar(
            "usuario_toggle",
            $usuario->usuario .
                " → " .
                ($usuario->activo ? "activo" : "inactivo"),
        );

        return back()->with(
            "mensaje",
            $usuario->activo ? "Usuario activado." : "Usuario desactivado.",
        );
    }

    public function destroy(Request $request, User $usuario)
    {
        if ($usuario->is($request->user())) {
            return back()->withErrors([
                "general" => "No puede eliminarse a sí mismo.",
            ]);
        }
        if ($this->dejariaSinAdmins($usuario, "cyber", false)) {
            return back()->withErrors([
                "general" => "Debe quedar al menos un administrador activo.",
            ]);
        }

        if ($usuario->cajeros()->exists()) {
            return back()->withErrors([
                "general" => "Reasigne primero los cajeros de este encargado.",
            ]);
        }
        $nombre = $usuario->usuario;
        $usuario->delete();
        Actividad::registrar("usuario_eliminar", $nombre);

        return redirect()
            ->route("admin.usuarios.index")
            ->with("mensaje", "Usuario $nombre eliminado.");
    }

    /** Marca como verificado a un cliente que no recibió el código. */
    public function verificar(User $usuario)
    {
        $usuario->forceFill(["email_verified_at" => now()])->save();
        Actividad::registrar("usuario_verificar_manual", $usuario->email);

        return back()->with(
            "mensaje",
            "Correo de {$usuario->usuario} marcado como verificado.",
        );
    }

    protected function validar(Request $request, ?User $u = null): array
    {
        $request->merge([
            "usuario" => mb_strtoupper(
                trim((string) $request->input("usuario")),
            ),
            "email" => mb_strtolower(trim((string) $request->input("email"))),
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
                "cyber_id" => "cyber",
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
        if ($datos['rol']==='cliente' && $request->boolean('edicion_cliente_enviada')) {
            $extra=$request->validate(['edicion_cliente'=>'nullable|array|max:4','edicion_cliente.*'=>['string',Rule::in(array_keys(\App\Support\EdicionCliente::GRUPOS))]]);
            $datos['edicion_cliente']=array_values(array_unique($extra['edicion_cliente']??[]));
        }
        if($datos['rol']!=='cliente')$datos['edicion_cliente']=null;
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
            ->doesntExist();
    }
}
