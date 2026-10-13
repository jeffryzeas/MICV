<?php

namespace App\Http\Controllers;

use App\Models\Actividad;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use lbuchs\WebAuthn\WebAuthn;

class PasskeyController extends Controller
{
    private function web(): WebAuthn
    {
        abort_unless(
            str_starts_with(config("chat.origin"), "https://") ||
                config("chat.rp_id") === "localhost",
            503,
            "Las llaves de acceso requieren HTTPS y APP_URL correcto.",
        );

        return new WebAuthn("Sistema JZ", config("chat.rp_id"), ["none"], true);
    }

    private function bin(string $s): string
    {
        $v = base64_decode(strtr($s, "-_", "+/"), true);
        if ($v === false) {
            throw ValidationException::withMessages([
                "passkey" => "Respuesta de llave inválida.",
            ]);
        }

        return $v;
    }

    private function origen(string $data): void
    {
        $j = json_decode($data, true);
        abort_unless(
            is_array($j) &&
                ($j["origin"] ?? "") === config("chat.origin") &&
                empty($j["crossOrigin"]),
            422,
            "Origen de la llave inválido.",
        );
    }

    private function reto(Request $r, string $tipo): array
    {
        $c = $r->session()->pull("jz_passkey_" . $tipo);
        abort_unless(
            $c &&
                $c["vence"] >= time() &&
                ($tipo !== "crear" ||
                    (int) $c["user"] === (int) $r->user()->id),
            422,
            "El reto venció. Intente otra vez.",
        );

        return $c;
    }

    public function index(Request $r)
    {
        return view("chat.passkeys", [
            "llaves" => DB::table("jz_passkeys")
                ->where("user_id", $r->user()->id)
                ->get(),
        ]);
    }

    public function opcionesCrear(Request $r)
    {
        $r->validate(["password" => "required|string|max:200"]);
        if (!Hash::check($r->input("password"), $r->user()->password)) {
            throw ValidationException::withMessages([
                "password" => "Contraseña incorrecta.",
            ]);
        }
        abort_if(
            DB::table("jz_passkeys")
                ->where("user_id", $r->user()->id)
                ->count() >= 10,
            422,
            "Elimine una llave antes de agregar otra.",
        );
        $w = $this->web();
        $args = $w->getCreateArgs(
            hash("sha256", "micv-user-" . $r->user()->id, true),
            $r->user()->email,
            $r->user()->name,
            120,
            true,
            true,
            null,
            DB::table("jz_passkeys")
                ->where("user_id", $r->user()->id)
                ->pluck("cred_id")
                ->map(fn($v) => $this->bin($v))
                ->all(),
        );
        $r->session()->put("jz_passkey_crear", [
            "reto" => base64_encode($w->getChallenge()->getBinaryString()),
            "user" => $r->user()->id,
            "vence" => time() + 120,
        ]);

        return response()->json($args)->header("Cache-Control", "no-store");
    }

    public function crear(Request $r)
    {
        $d = $r->validate([
            "clientDataJSON" => "required|string|max:20000",
            "attestationObject" => "required|string|max:100000",
            "nombre" => "required|string|max:80",
        ]);
        $c = $this->reto($r, "crear");
        try {
            $data = $this->bin($d["clientDataJSON"]);
            $this->origen($data);
            $v = $this->web()->processCreate(
                $data,
                $this->bin($d["attestationObject"]),
                base64_decode($c["reto"]),
                true,
                true,
                false,
            );
            DB::table("jz_passkeys")->insert([
                "user_id" => $r->user()->id,
                "cred_hash" => hash("sha256", $v->credentialId),
                "cred_id" => base64_encode($v->credentialId),
                "public_key" => $v->credentialPublicKey,
                "contador" => $v->signatureCounter ?? 0,
                "nombre" => $d["nombre"],
                "created_at" => now(),
                "updated_at" => now(),
            ]);
        } catch (\Throwable $e) {
            report($e);
            throw ValidationException::withMessages([
                "passkey" => "No se pudo registrar la llave. Intente otra vez.",
            ]);
        }
        Actividad::registrar("passkey_crear");

        return response()->json(["ok" => true]);
    }

    public function opcionesLogin(Request $r)
    {
        $w = $this->web();
        $args = $w->getGetArgs([], 120, true, true, true, true, true, true);
        $r->session()->put("jz_passkey_login", [
            "reto" => base64_encode($w->getChallenge()->getBinaryString()),
            "vence" => time() + 120,
        ]);

        return response()->json($args)->header("Cache-Control", "no-store");
    }

    public function entrar(Request $r)
    {
        $d = $r->validate([
            "rawId" => "required|string|max:3000",
            "clientDataJSON" => "required|string|max:20000",
            "authenticatorData" => "required|string|max:20000",
            "signature" => "required|string|max:10000",
        ]);
        $c = $this->reto($r, "login");
        try {
            $u = DB::transaction(function () use ($d, $c) {
                $k = DB::table("jz_passkeys")
                    ->where(
                        "cred_hash",
                        hash("sha256", $this->bin($d["rawId"])),
                    )
                    ->lockForUpdate()
                    ->first();
                if (!$k) {
                    throw new \RuntimeException("Credencial desconocida");
                }
                $u = User::findOrFail($k->user_id);
                if (
                    !$u->activo ||
                    ($u->esCyber() && (!$u->cyber || !$u->cyber->activo))
                ) {
                    throw new \RuntimeException("Cuenta desactivada");
                }
                $client = $this->bin($d["clientDataJSON"]);
                $this->origen($client);
                $w = $this->web();
                $w->processGet(
                    $client,
                    $this->bin($d["authenticatorData"]),
                    $this->bin($d["signature"]),
                    $k->public_key,
                    base64_decode($c["reto"]),
                    (int) $k->contador,
                    true,
                    true,
                );
                DB::table("jz_passkeys")
                    ->where("id", $k->id)
                    ->update([
                        "contador" => $w->getSignatureCounter() ?? 0,
                        "updated_at" => now(),
                    ]);

                return $u;
            });
        } catch (\Throwable $e) {
            report($e);
            throw ValidationException::withMessages([
                "passkey" =>
                    "No se pudo verificar el acceso. Puede entrar con su contraseña.",
            ]);
        }
        Auth::login($u);
        $r->session()->regenerate();
        $u->forceFill(["ultimo_acceso" => now()])->save();
        Actividad::registrar("login_passkey");

        return response()->json(["url" => route("dashboard")]);
    }

    public function eliminar(Request $r, int $llave)
    {
        $r->validate(["password" => "required|string|max:200"]);
        abort_unless(
            Hash::check($r->input("password"), $r->user()->password),
            422,
            "Contraseña incorrecta.",
        );
        DB::table("jz_passkeys")
            ->where("user_id", $r->user()->id)
            ->where("id", $llave)
            ->delete();
        Actividad::registrar("passkey_eliminar");

        return back()->with("mensaje", "Llave eliminada.");
    }
}
