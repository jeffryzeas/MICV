<?php

namespace App\Http\Controllers;

use App\Models\Actividad;
use App\Models\Cliente;
use App\Models\User;
use App\Services\Desktop\{FullClientSync, LocalBusinessJournal};
use App\Support\EdicionCliente;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AccesoClienteController extends Controller
{
    public function formulario(Request $r, Cliente $cliente)
    {
        $permiso=$r->user()->tienePermiso("clientes.acceso")?"clientes.acceso":"clientes.permisos";
        $this->autorizar($r, $cliente, $permiso);

        return view("chat.acceso", compact("cliente"));
    }

    private function autorizar(Request $r, Cliente $c, string $permiso="clientes.acceso"): void
    {
        abort_unless(
            ($r->user()->esAdmin() ||
                ($r->user()->esEncargado() &&
                    $r->user()->tienePermiso($permiso))) &&
                $c->esVisiblePara($r->user()),
            403,
        );
    }

    public function permisos(Request $r, Cliente $cliente)
    {
        $this->aplicarPermisos($r, $cliente);
        Actividad::registrar('cliente_permisos_edicion', '#'.$cliente->id);
        return back()->with('mensaje', 'Permisos de edición del cliente actualizados.');
    }

    public function aplicarPermisos(Request $r, Cliente $cliente, ?array $base = null): void
    {
        $this->autorizar($r, $cliente, 'clientes.permisos');
        $d = $r->validate([
            'edicion_cliente' => 'nullable|array|max:4',
            'edicion_cliente.*' => ['string', Rule::in(array_keys(EdicionCliente::GRUPOS))],
        ]);
        $local = $r->attributes->has('local_permissions');
        $tenant = (int) getenv('MICV_LOCAL_TENANT');
        if ($local) {
            abort_unless($tenant > 0 && $cliente->cybers()->whereKey($tenant)->exists(), 403,
                'Abra el espacio del negocio para modificar los permisos.');
        }
        DB::transaction(function () use ($r, $cliente, $base, $local, $tenant, $d) {
            $ficha = Cliente::query()->whereKey($cliente->id)->lockForUpdate()->firstOrFail();
            $cuenta = User::query()->whereKey($ficha->user_id)->lockForUpdate()->first();
            abort_unless($cuenta && $cuenta->esCliente(), 422,
                'El cliente necesita una cuenta personal antes de configurar su edición.');
            $anterior = EdicionCliente::permitidos($cuenta);
            $revision = FullClientSync::fingerprint($anterior);
            abort_if($base && ((int) $base['user_id'] !== $cuenta->id || !hash_equals($revision, $base['revision'])), 409,
                'La cuenta o sus permisos de edición cambiaron en la web.');
            $grupos = array_values(array_intersect(array_keys(EdicionCliente::GRUPOS), $d['edicion_cliente'] ?? []));
            $cuenta->update(['edicion_cliente' => $grupos]);
            if ($local && $anterior !== $grupos) {
                LocalBusinessJournal::record($r, $tenant, 'client.permissions.update', $ficha->id, [
                    'id' => LocalBusinessJournal::reference($r, 'client.full.create', $ficha->id), 'user_id' => $cuenta->id, 'revision' => $revision, 'edicion_cliente' => $grupos,
                ]);
            }
        });
    }

    public function crear(Request $r, Cliente $cliente)
    {
        $this->autorizar($r, $cliente);
        $r->merge([
            "email" => mb_strtolower(trim((string) $r->input("email"))),
        ]);
        $d = $r->validate([
            "email" => [
                "required",
                "email",
                "max:150",
                Rule::unique("users", "email"),
            ],
        ]);
        $enlace = DB::transaction(function () use ($d, $cliente) {
            $c = Cliente::whereKey($cliente->id)
                ->lockForUpdate()
                ->firstOrFail();
            if ($c->user_id) {
                throw ValidationException::withMessages([
                    "email" =>
                        "Este cliente ya tiene cuenta. No se cambia su acceso desde el Cyber.",
                ]);
            }
            $u = User::create([
                "name" => $c->nombreCompleto(),
                "usuario" => "CL" . strtoupper(Str::random(12)),
                "email" => $d["email"],
                "password" => Str::random(64),
                "rol" => "cliente",
                "activo" => true,
                "cyber_id" => null,
            ]);
            $c->update(["user_id" => $u->id, "correo" => $d["email"]]);
            $token = Str::random(64);
            DB::table("jz_invitaciones")->insert([
                "user_id" => $u->id,
                "token" => hash("sha256", $token),
                "vence" => now()->addDay(),
                "created_at" => now(),
                "updated_at" => now(),
            ]);
            Actividad::registrar("cliente_acceso_crear", "#" . $c->id);

            return route("acceso.activar", $token);
        });

        return back()
            ->with("invitacion", $enlace)
            ->with(
                "mensaje",
                "Acceso creado. Entregue el enlace de un solo uso a la persona; vence en 24 horas.",
            );
    }

    public function activar(string $token)
    {
        $this->invitacion($token);

        return response()
            ->view("chat.activar", compact("token"))
            ->header("Cache-Control", "no-store")
            ->header("Referrer-Policy", "no-referrer");
    }

    private function invitacion(string $token)
    {
        abort_unless(preg_match('/^[A-Za-z0-9]{64}$/', $token), 404);
        $i = DB::table("jz_invitaciones")
            ->where("token", hash("sha256", $token))
            ->where("vence", ">", now())
            ->first();
        abort_unless(
            $i,
            410,
            "El enlace venció o ya fue utilizado. Recupere el acceso mediante su correo.",
        );

        return $i;
    }

    public function guardar(Request $r, string $token)
    {
        $d = $r->validate([
            "password" => [
                "required",
                "confirmed",
                Password::min(12)->letters()->numbers(),
            ],
        ]);
        $u = DB::transaction(function () use ($token, $d) {
            $i = DB::table("jz_invitaciones")
                ->where("token", hash("sha256", $token))
                ->where("vence", ">", now())
                ->lockForUpdate()
                ->first();
            abort_unless($i, 410);
            $u = User::findOrFail($i->user_id);
            abort_unless($u->activo && $u->esCliente(), 403);
            $u->update(["password" => $d["password"]]);
            DB::table("jz_invitaciones")->where("id", $i->id)->delete();

            return $u;
        });
        Auth::login($u);
        $r->session()->regenerate();

        return redirect()
            ->route("verificar.mostrar")
            ->with(
                "mensaje",
                "Contraseña creada. Verifique su correo para entrar a sus trabajos y mensajes.",
            );
    }
}
