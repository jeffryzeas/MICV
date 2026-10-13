<?php
namespace App\Http\Controllers;
use App\Models\{JzChat, JzMensaje, Actividad};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
class ChatSupervisionController extends Controller
{
    private function acceso(Request $r): void
    {
        abort_unless($r->user()->esAdmin(), 403);
    }
    public function index(Request $r)
    {
        $this->acceso($r);
        $q = JzChat::with(["uno.cyber", "dos.cyber"]);
        if ($s = trim((string) $r->query("q"))) {
            $q->where(
                fn($q) => $q
                    ->whereHas(
                        "uno",
                        fn($q) => $q
                            ->where("name", "like", "%" . $s . "%")
                            ->orWhereHas(
                                "cyber",
                                fn($q) => $q->where(
                                    "nombre",
                                    "like",
                                    "%" . $s . "%",
                                ),
                            ),
                    )
                    ->orWhereHas(
                        "dos",
                        fn($q) => $q
                            ->where("name", "like", "%" . $s . "%")
                            ->orWhereHas(
                                "cyber",
                                fn($q) => $q->where(
                                    "nombre",
                                    "like",
                                    "%" . $s . "%",
                                ),
                            ),
                    ),
            );
        }
        return response()
            ->view("chat.supervision", [
                "chats" => $q
                    ->latest("updated_at")
                    ->paginate(30)
                    ->withQueryString(),
            ])
            ->header("Cache-Control", "no-store");
    }
    public function ver(Request $r, JzChat $chat)
    {
        $this->acceso($r);
        Actividad::registrar("chat.supervision", "Conversación #" . $chat->id);
        return response()
            ->view("chat.supervision", [
                "chat" => $chat,
                "mensajes" => $chat->mensajes()->latest("id")->paginate(50),
            ])
            ->header("Cache-Control", "no-store");
    }
    public function archivo(Request $r, JzMensaje $mensaje)
    {
        $this->acceso($r);
        abort_if(
            $mensaje->eliminado_en ||
                !$mensaje->archivo ||
                !Storage::disk("local")->exists($mensaje->archivo),
            404,
        );
        Actividad::registrar(
            "chat.supervision.archivo",
            "Mensaje #" . $mensaje->id . "; conversación #" . $mensaje->chat_id,
        );
        return Storage::disk("local")->download(
            $mensaje->archivo,
            $mensaje->nombre,
            [
                "Cache-Control" => "private, no-store",
                "X-Content-Type-Options" => "nosniff",
                "Content-Security-Policy" => "sandbox; default-src 'none'",
            ],
        );
    }
}
