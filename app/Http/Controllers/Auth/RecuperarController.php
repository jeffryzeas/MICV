<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Actividad;
use App\Models\User;
use App\Services\Recaptcha;
use App\Services\Verificacion;
use App\Support\AntiBot;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

/** "¿Olvidó su contraseña?": código de 6 dígitos al correo. */
class RecuperarController extends Controller
{
    public function mostrar()
    {
        return view("auth.recuperar");
    }

    public function enviar(Request $request)
    {
        $request->validate(
            ["email" => ["required", "email"]],
            [],
            ["email" => "correo"],
        );
        if (AntiBot::esRobot($request)) {
            Actividad::registrar(
                "robot_bloqueado",
                "recuperar · " . $request->ip(),
            );

            return back()
                ->withInput(
                    $request->except("password", "password_confirmation"),
                )
                ->withErrors([
                    "general" =>
                        "No pudimos procesar el formulario. Espere unos segundos e intente de nuevo.",
                ]);
        }
        if (
            $error = Recaptcha::verificar(
                $request->input("recaptcha_token"),
                "recuperar",
            )
        ) {
            return back()
                ->withInput()
                ->withErrors(["general" => $error]);
        }
        $email = mb_strtolower(trim($request->input("email")));
        $u = User::where("email", $email)->where("activo", true)->first();
        $debug = null;
        if ($u && Verificacion::puedeReenviar($u, "recuperar") === 0) {
            $r = Verificacion::enviar($u, "recuperar");
            $debug = $r["codigo_debug"];
            Actividad::registrar("recuperar_solicitado", $email);
            if (!$r["enviado"] && !$debug) {
                return back()
                    ->withInput()
                    ->withErrors([
                        "general" =>
                            "No se pudo enviar el correo en este momento. Intente más tarde o hable con el administrador.",
                    ]);
            }
        }

        // Mismo mensaje exista o no la cuenta.
        return redirect()
            ->route("recuperar.codigo", ["email" => $email])
            ->with(
                "mensaje",
                "Si el correo está registrado, le llegará un código de 6 dígitos.",
            )
            ->with("codigo_debug", $debug);
    }

    public function codigo(Request $request)
    {
        return view("auth.recuperar-codigo", [
            "email" => (string) $request->query("email"),
        ]);
    }

    public function restablecer(Request $request)
    {
        $d = $request->validate(
            [
                "email" => ["required", "email"],
                "codigo" => ["required", "string", "max:10"],
                "password" => ["required", "confirmed", Password::min(8)],
            ],
            [],
            ["email" => "correo", "password" => "nueva contraseña"],
        );
        $u = User::where("email", mb_strtolower(trim($d["email"])))->first();
        $error = $u
            ? Verificacion::comprobar($u, $d["codigo"], "recuperar")
            : "El código no es válido.";
        if ($error) {
            return back()
                ->withInput($request->only("email"))
                ->withErrors(["codigo" => $error]);
        }
        DB::transaction(function () use ($u, $d) {
            $u->update([
                "password" => $d["password"],
                "remember_token" => Str::random(60),
            ]);
            DB::table("jz_invitaciones")->where("user_id", $u->id)->delete();
            DB::table("jz_passkeys")->where("user_id", $u->id)->delete();
            DB::table("sessions")->where("user_id", $u->id)->delete();
        });
        $u->forceFill([
            "email_verified_at" => $u->email_verified_at ?? now(),
        ])->save();
        Actividad::registrar("recuperar_completado", $u->email);

        return redirect()
            ->route("login")
            ->with(
                "mensaje",
                "Contraseña cambiada. Por seguridad se cerraron las sesiones y se revocaron las llaves anteriores; puede registrarlas de nuevo en su perfil.",
            );
    }
}
