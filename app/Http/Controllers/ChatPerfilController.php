<?php
namespace App\Http\Controllers;
use App\Models\User;
use App\Services\{ChatAcceso, FotoProducto};
use App\Support\Archivos;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Storage, DB, Cache};
class ChatPerfilController extends Controller
{
    public static function datos(User $u): array
    {
        $c = $u->esCliente() ? $u->cliente : null;
        $foto = $c ? $c->foto : $u->chat_foto;
        $cfg = $u->esAdmin() ? \App\Models\Configuracion::actualSegura() : null;
        return [
            "id" => $u->id,
            "nombre" => $u->esAdmin()
                ? ($cfg?->nombre_negocio ?:
                "Mi CV")
                : ($c?->nombreCompleto() ?:
                $u->name),
            "usuario" => $u->esAdmin() ? "Plataforma" : $u->usuario,
            "rol" => $u->esAdmin()
                ? "Sistema JZ"
                : $u->cyber?->nombre ?? "Cliente",
            "foto" => $foto
                ? route("chat.perfil.foto", [
                    $u,
                    "v" => ($c?->updated_at ?? $u->updated_at)?->timestamp,
                ])
                : null,
            "info" => $u->chat_info ?? "",
            "verificado" => $u->verificado() || ($u->activo && ($u->esAdmin() || ($u->esCyber() && $u->cyber?->activo))),
            "verificacion_tipo" => ($u->activo && ($u->esAdmin() || ($u->esCyber() && $u->cyber?->activo))) ? "Cuenta institucional" : ($u->verificado() ? "Correo verificado" : null),
            "online" => (bool) Cache::get("jz-online-" . $u->id),
            "cliente" => $u->esCliente(),
            "plataforma" => $u->esAdmin(),
        ];
    }
    private function autorizar(Request $r, User $usuario): void
    {
        abort_unless(
            ChatAcceso::habilitado($r->user()) &&
                ($usuario->id === $r->user()->id ||
                    ChatAcceso::puede($r->user(), $usuario)),
            403,
        );
    }
    public function ver(Request $r, User $usuario)
    {
        $this->autorizar($r, $usuario);
        return response()
            ->json(self::datos($usuario))
            ->header("Cache-Control", "no-store");
    }
    public function guardar(Request $r)
    {
        abort_unless(ChatAcceso::habilitado($r->user()), 403);
        $d = $r->validate([
            "nombre" => "nullable|string|max:150",
            "info" => "nullable|string|max:200",
            "foto" => "nullable|image|mimes:jpg,jpeg,png,webp|max:51200",
            "quitar_foto" => "nullable|boolean",
        ]);
        $u = $r->user();
        $c = $u->esCliente() ? $u->cliente : null;
        abort_if(
            $u->esCliente() && !$c,
            422,
            "Complete primero su ficha de cliente.",
        );
        $old = $c ? $c->foto : $u->chat_foto;
        $new = null;
        if ($r->hasFile("foto")) {
            $new = FotoProducto::guardar($r->file("foto"));
        }
        try {
            DB::transaction(function () use ($r, $u, $d, $new, $old, $c) {
                $u->forceFill([
                    "chat_info" => array_key_exists("info", $d)
                        ? $d["info"]
                        : $u->chat_info,
                ]);
                if (
                    !$u->esCliente() &&
                    isset($d["nombre"]) &&
                    trim($d["nombre"]) !== ""
                ) {
                    $u->name = trim($d["nombre"]);
                }
                if ($new || $r->boolean("quitar_foto")) {
                    if ($c) {
                        $c->foto = $new;
                        $c->save();
                    } else {
                        $u->chat_foto = $new;
                    }
                }
                $u->save();
                if ($old && ($c ? $c->foto : $u->chat_foto) !== $old) {
                    DB::afterCommit(
                        fn() => Storage::disk(Archivos::DISCO)->delete($old),
                    );
                }
            });
        } catch (\Throwable $e) {
            if ($new) {
                Storage::disk(Archivos::DISCO)->delete($new);
            }
            throw $e;
        }
        return response()->json(self::datos($u->fresh()));
    }
    public function foto(Request $r, User $usuario)
    {
        $this->autorizar($r, $usuario);
        $foto = $usuario->esCliente()
            ? $usuario->cliente?->foto
            : $usuario->chat_foto;
        abort_unless(
            $foto && Storage::disk(Archivos::DISCO)->exists($foto),
            404,
        );
        return response()->file(Storage::disk(Archivos::DISCO)->path($foto), [
            "Content-Type" =>
                Storage::disk(Archivos::DISCO)->mimeType($foto) ?: "image/jpeg",
            "Cache-Control" => "private, no-store",
            "X-Content-Type-Options" => "nosniff",
        ]);
    }
}
