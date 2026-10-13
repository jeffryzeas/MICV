<?php

namespace App\Http\Controllers;

use App\Models\Actividad;
use App\Services\Verificacion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class PerfilController extends Controller
{
    public function editar(Request $request)
    {
        return view("perfil", ["user" => $request->user()]);
    }

    public function actualizar(Request $request)
    {
        $user = $request->user();
        $datos = $request->validate(
            [
                "name" => ["required", "string", "max:150"],
                "email" => [
                    "required",
                    "email",
                    "max:150",
                    Rule::unique("users", "email")->ignore($user->id),
                ],
            ],
            [],
            ["name" => "nombre"],
        );

        $nuevoCorreo = mb_strtolower($datos["email"]);
        $cambioCorreo = $nuevoCorreo !== $user->email;
        // El nombre de un usuario registrado sale de su CV ("Mis datos"): así siempre coinciden.
        $nombre =
            $user->esCliente() && $user->cliente
                ? ($user->cliente->nombreCompleto() ?:
                $datos["name"])
                : $datos["name"];
        $user->update(["name" => $nombre, "email" => $nuevoCorreo]);

        // Un cliente que cambia su correo debe verificarlo de nuevo con un código.
        if ($cambioCorreo && $user->esCliente()) {
            $user->forceFill(["email_verified_at" => null])->save();
            $user->cliente?->update(["correo" => $nuevoCorreo]);
            $r = Verificacion::enviar($user);

            return redirect()
                ->route("verificar.mostrar")
                ->with(
                    $r["enviado"]
                        ? [
                            "mensaje" =>
                                "Le enviamos un código a su nuevo correo para verificarlo.",
                        ]
                        : [
                            "aviso_correo" => $r["error"],
                            "codigo_debug" => $r["codigo_debug"],
                        ],
                );
        }

        return back()->with("mensaje", "Datos actualizados.");
    }

    public function password(Request $request)
    {
        $user = $request->user();
        $request->validate(
            [
                "actual" => ["required"],
                "password" => ["required", "confirmed", Password::min(8)],
            ],
            [],
            ["actual" => "contraseña actual", "password" => "nueva contraseña"],
        );

        if (!Hash::check($request->input("actual"), $user->password)) {
            return back()->withErrors([
                "actual" => "La contraseña actual no es correcta.",
            ]);
        }

        $user->update(["password" => $request->input("password")]);
        DB::table("jz_invitaciones")->where("user_id", $user->id)->delete();
        Actividad::registrar("cambio_password");

        return back()->with("mensaje", "Contraseña cambiada.");
    }
}
