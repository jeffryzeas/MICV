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
        return [
            "id" => $u->id,
            "nombre" => $u->name,
            "usuario" => $u->usuario,
            "rol" => $u->esAdmin()
                ? "Sistema JZ"
                : $u->cyber?->nombre ?? "Cliente",
            "foto" => $u->chat_foto
                ? route("chat.perfil.foto", [
                    $u,
                    "v" => $u->updated_at?->timestamp,
                ])
                : null,
            "info" => $u->chat_info ?? "",
            "verificado" => $u->verificado(),
            "online" => (bool) Cache::get("jz-online-" . $u->id),
            "cliente" => $u->esCliente(),
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
            "foto" => "nullable|image|mimes:jpg,jpeg,png,webp|max:8192",
            "quitar_foto" => "nullable|boolean",
        ]);
        $u = $r->user();
        $old = $u->chat_foto;
        $new = null;
        if ($r->hasFile("foto")) {
            $new = FotoProducto::guardar($r->file("foto"));
        }
        try {
            DB::transaction(function () use ($r, $u, $d, $new, $old) {
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
                    $u->chat_foto = $new;
                }
                $u->save();
                if ($old && $u->chat_foto !== $old) {
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
        abort_unless(
            $usuario->chat_foto &&
                Storage::disk(Archivos::DISCO)->exists($usuario->chat_foto),
            404,
        );
        return response()->file(
            Storage::disk(Archivos::DISCO)->path($usuario->chat_foto),
            [
                "Content-Type" => "image/jpeg",
                "Cache-Control" => "private, no-store",
                "X-Content-Type-Options" => "nosniff",
            ],
        );
    }
}
