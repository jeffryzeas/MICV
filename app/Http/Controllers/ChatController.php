<?php

namespace App\Http\Controllers;

use App\Models\JzChat;
use App\Models\JzMensaje;
use App\Models\User;
use App\Services\ChatAcceso;
use App\Services\ChatArchivo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ChatController extends Controller
{
    public function index(Request $r)
    {
        abort_unless(ChatAcceso::habilitado($r->user()), 403);

        return response()
            ->view("chat.index")
            ->header("Cache-Control", "no-store");
    }

    private function autorizar(Request $r, JzChat $chat): void
    {
        abort_unless(
            $chat->miembro($r->user()) &&
                ChatAcceso::puede($r->user(), $chat->otro($r->user())),
            403,
        );
    }

    public function lista(Request $r)
    {
        $u = $r->user();
        abort_unless(ChatAcceso::habilitado($u), 403);
        $chats = JzChat::with(["uno.cyber", "dos.cyber"])
            ->where(
                fn($q) => $q
                    ->where("uno_id", $u->id)
                    ->orWhere("dos_id", $u->id),
            )
            ->orderByDesc("updated_at")
            ->get()
            ->filter(fn($c) => ChatAcceso::puede($u, $c->otro($u)));
        \Illuminate\Support\Facades\Cache::put(
            "jz-online-" . $u->id,
            true,
            now()->addSeconds(45),
        );
        $filas = $chats
            ->map(function ($c) use ($u) {
                $o = $c->otro($u);

                $ultimo = $c
                    ->mensajes()
                    ->whereNotIn(
                        "id",
                        DB::table("jz_mensajes_ocultos")
                            ->where("user_id", $u->id)
                            ->select("mensaje_id"),
                    )
                    ->latest("id")
                    ->first();
                return [
                    "perfil" => ChatPerfilController::datos($o),
                    "ultimo" => $ultimo?->eliminado_en
                        ? "Mensaje eliminado"
                        : ($ultimo?->texto ?:
                        $ultimo?->nombre ?? ""),
                    "fecha" => $ultimo?->created_at?->toIso8601String(),
                    "id" => $c->id,
                    "nombre" => ChatPerfilController::datos($o)["nombre"],
                    "rol" => $o->esAdmin()
                        ? "Sistema JZ"
                        : $o->cyber?->nombre ?? "Cliente",
                    "nuevos" => $c
                        ->mensajes()
                        ->where("id", ">", $c->{$c->lectura($u)})
                        ->whereNull("eliminado_en")
                        ->whereNotIn(
                            "id",
                            DB::table("jz_mensajes_ocultos")
                                ->where("user_id", $u->id)
                                ->select("mensaje_id"),
                        )
                        ->where("user_id", "!=", $u->id)
                        ->count(),
                ];
            })
            ->values();

        return response()
            ->json(["chats" => $filas, "total" => $filas->sum("nuevos")])
            ->header("Cache-Control", "no-store");
    }

    public function contactos(Request $r)
    {
        $u = $r->user();
        abort_unless(ChatAcceso::habilitado($u), 403);
        $q = mb_substr(trim((string) $r->query("q")), 0, 80);

        return response()->json(
            ChatAcceso::contactos($u)
                ->with("cyber")
                ->when(
                    $q,
                    fn($w) => $w->where(
                        fn($n) => $n
                            ->where("name", "like", "%" . $q . "%")
                            ->orWhereHas(
                                "cliente",
                                fn($c) => $c->where(
                                    fn($n) => $n
                                        ->where(
                                            "primer_nombre",
                                            "like",
                                            "%" . $q . "%",
                                        )
                                        ->orWhere(
                                            "primer_apellido",
                                            "like",
                                            "%" . $q . "%",
                                        ),
                                ),
                            ),
                    ),
                )
                ->orderBy("name")
                ->limit(80)
                ->get()
                ->filter(fn($o) => ChatAcceso::puede($u, $o))
                ->map(fn($o) => ChatPerfilController::datos($o))
                ->values(),
        );
    }

    public function abrir(Request $r)
    {
        $d = $r->validate(["user_id" => "required|integer"]);
        $o = User::findOrFail($d["user_id"]);
        abort_unless(ChatAcceso::puede($r->user(), $o), 403);
        $ids = [$r->user()->id, $o->id];
        sort($ids);
        $c = JzChat::firstOrCreate(["uno_id" => $ids[0], "dos_id" => $ids[1]]);

        return response()->json(["id" => $c->id]);
    }

    public function mensajes(Request $r, JzChat $chat)
    {
        $this->autorizar($r, $chat);
        $d = $r->validate([
            "antes" => "nullable|integer|min:1",
            "despues" => "nullable|integer|min:0",
        ]);
        $q = $chat->mensajes();
        if (!empty($d["antes"])) {
            $q->where("id", "<", $d["antes"]);
        }
        if (isset($d["despues"])) {
            $q->where("id", ">", $d["despues"]);
        }
        $incremental = isset($d["despues"]);
        $filas = $incremental
            ? $q->orderBy("id")->limit(60)->get()
            : $q->orderByDesc("id")->limit(60)->get()->reverse()->values();
        $u = $r->user();
        $otroLeido =
            $chat->{(int) $chat->uno_id === (int) $u->id
                ? "dos_leido"
                : "uno_leido"};

        return response()
            ->json([
                "mensajes" => $filas->map(
                    fn($m) => $this->dato($m, $r->user(), (int) $otroLeido),
                ),
                "ultimo" => (int) $chat->mensajes()->max("id"),
                "otro_leido" => (int) $otroLeido,
            ])
            ->header("Cache-Control", "no-store");
    }

    private function dato(JzMensaje $m, User $u, int $leido = 0): array
    {
        $oculto = DB::table("jz_mensajes_ocultos")
            ->where("mensaje_id", $m->id)
            ->where("user_id", $u->id)
            ->exists();
        $eliminado = (bool) $m->eliminado_en;
        return [
            "id" => $m->id,
            "mio" => (int) $m->user_id === (int) $u->id,
            "oculto" => $oculto,
            "eliminado" => $eliminado,
            "editado" => (bool) $m->editado_en,
            "texto" => $oculto || $eliminado ? "" : $m->texto,
            "nombre" => $oculto || $eliminado ? null : $m->nombre,
            "mime" => $oculto || $eliminado ? null : $m->mime,
            "bytes" => $oculto || $eliminado ? null : $m->bytes,
            "miniatura" =>
                !$oculto && !$eliminado && $m->miniatura
                    ? route("chat.archivo", [$m, "mini" => 1])
                    : null,
            "archivo" =>
                !$oculto && !$eliminado && $m->archivo
                    ? route("chat.archivo", $m)
                    : null,
            "sistema" => $m->sistema,
            "hora" => $m->created_at->toIso8601String(),
            "leido" => $m->id <= $leido,
        ];
    }
    public function sincronizar(Request $r, JzChat $chat)
    {
        $this->autorizar($r, $chat);
        $d = $r->validate([
            "ids" => "required|array|max:200",
            "ids.*" => "integer|min:1",
        ]);
        $col =
            (int) $chat->uno_id === (int) $r->user()->id
                ? "dos_leido"
                : "uno_leido";
        return response()
            ->json([
                "mensajes" => $chat
                    ->mensajes()
                    ->whereIn("id", $d["ids"])
                    ->get()
                    ->map(
                        fn($m) => $this->dato(
                            $m,
                            $r->user(),
                            (int) $chat->$col,
                        ),
                    ),
            ])
            ->header("Cache-Control", "no-store");
    }
    public function editarMensaje(Request $r, JzMensaje $mensaje)
    {
        $this->autorizar($r, $mensaje->chat);
        abort_unless((int) $mensaje->user_id === (int) $r->user()->id, 403);
        $d = $r->validate(["texto" => "required|string|max:6000"]);
        abort_unless(trim($d["texto"]) !== "", 422);
        DB::transaction(function () use ($mensaje, $d) {
            $m = JzMensaje::lockForUpdate()->findOrFail($mensaje->id);
            abort_if($m->eliminado_en, 409);
            $m->update(["texto" => trim($d["texto"]), "editado_en" => now()]);
        });
        return response()->json(["ok" => true]);
    }
    public function eliminarMensaje(Request $r, JzMensaje $mensaje)
    {
        $this->autorizar($r, $mensaje->chat);
        $d = $r->validate(["para" => "required|in:mi,todos"]);
        if ($d["para"] === "mi") {
            DB::table("jz_mensajes_ocultos")->insertOrIgnore([
                "mensaje_id" => $mensaje->id,
                "user_id" => $r->user()->id,
            ]);
        } else {
            abort_unless((int) $mensaje->user_id === (int) $r->user()->id, 403);
            DB::transaction(function () use ($mensaje) {
                $m = JzMensaje::lockForUpdate()->findOrFail($mensaje->id);
                $files = array_filter([$m->archivo, $m->miniatura]);
                $m->update([
                    "texto" => null,
                    "archivo" => null,
                    "miniatura" => null,
                    "nombre" => null,
                    "mime" => null,
                    "bytes" => 0,
                    "eliminado_en" => now(),
                ]);
                DB::afterCommit(fn() => Storage::disk("local")->delete($files));
            });
        }
        return response()->json(["ok" => true]);
    }

    public function archivos(Request $r, JzChat $chat)
    {
        $this->autorizar($r, $chat);
        $d = $r->validate(["antes" => "nullable|integer|min:1"]);
        $rows = $chat
            ->mensajes()
            ->whereNull("eliminado_en")
            ->whereNotIn(
                "id",
                DB::table("jz_mensajes_ocultos")
                    ->where("user_id", $r->user()->id)
                    ->select("mensaje_id"),
            )
            ->whereNotNull("archivo")
            ->when(
                $d["antes"] ?? null,
                fn($q, $id) => $q->where("id", "<", $id),
            )
            ->orderByDesc("id")
            ->limit(31)
            ->get();
        $mas = $rows->count() > 30;
        $rows = $rows->take(30);
        return response()
            ->json([
                "archivos" => $rows
                    ->map(
                        fn($m) => [
                            "id" => $m->id,
                            "nombre" => $m->nombre,
                            "mime" => $m->mime,
                            "bytes" => $m->bytes,
                            "archivo" => route("chat.archivo", $m),
                            "miniatura" => $m->miniatura
                                ? route("chat.archivo", [$m, "mini" => 1])
                                : null,
                        ],
                    )
                    ->values(),
                "mas" => $mas,
                "antes" => $rows->last()?->id,
            ])
            ->header("Cache-Control", "no-store");
    }

    public function leer(Request $r, JzChat $chat)
    {
        $this->autorizar($r, $chat);
        $d = $r->validate(["hasta" => "required|integer|min:0"]);
        $v = min($d["hasta"], (int) $chat->mensajes()->max("id"));
        $col = $chat->lectura($r->user());
        JzChat::whereKey($chat->id)
            ->where($col, "<", $v)
            ->update([$col => $v]);

        return response()->json(["ok" => true]);
    }

    public function enviar(Request $r, JzChat $chat)
    {
        $this->autorizar($r, $chat);
        $d = $r->validate([
            "texto" => "nullable|string|max:6000",
            "clave" => "required|uuid",
            "archivo" => "nullable|file|max:51200",
            "sistema" => "nullable|boolean",
        ]);
        abort_unless(
            trim($d["texto"] ?? "") !== "" || $r->hasFile("archivo"),
            422,
            "Escriba un mensaje o adjunte un archivo.",
        );
        $prev = JzMensaje::where("user_id", $r->user()->id)
            ->where("clave", $d["clave"])
            ->first();
        if ($prev) {
            abort_unless((int) $prev->chat_id === (int) $chat->id, 409);

            return response()->json(["ok" => true]);
        }
        $archivo = $r->hasFile("archivo")
            ? ChatArchivo::guardar($r->file("archivo"))
            : [];
        try {
            DB::transaction(function () use ($r, $chat, $d, $archivo) {
                $chat->lockForUpdate()->findOrFail($chat->id);
                $msg = JzMensaje::firstOrCreate(
                    ["user_id" => $r->user()->id, "clave" => $d["clave"]],
                    [
                        "chat_id" => $chat->id,
                        "texto" => trim($d["texto"] ?? ""),
                        "sistema" =>
                            $r->user()->esAdmin() && $r->boolean("sistema"),
                    ] + $archivo,
                );
                abort_unless((int) $msg->chat_id === (int) $chat->id, 409);
                if (!$msg->wasRecentlyCreated && isset($archivo["archivo"])) {
                    Storage::disk("local")->delete(
                        array_filter([
                            $archivo["archivo"],
                            $archivo["miniatura"] ?? null,
                        ]),
                    );
                }
                $chat->touch();
            });
        } catch (\Throwable $e) {
            if (isset($archivo["archivo"])) {
                Storage::disk("local")->delete(
                    array_filter([
                        $archivo["archivo"],
                        $archivo["miniatura"] ?? null,
                    ]),
                );
            }
            throw $e;
        }

        return response()->json(["ok" => true], 201);
    }

    public function archivo(Request $r, JzMensaje $mensaje)
    {
        $this->autorizar($r, $mensaje->chat);
        abort_if(
            $mensaje->eliminado_en ||
                DB::table("jz_mensajes_ocultos")
                    ->where("mensaje_id", $mensaje->id)
                    ->where("user_id", $r->user()->id)
                    ->exists(),
            404,
        );
        abort_unless(
            $mensaje->archivo &&
                Storage::disk("local")->exists($mensaje->archivo),
            404,
        );
        $path =
            $r->boolean("mini") && $mensaje->miniatura
                ? $mensaje->miniatura
                : $mensaje->archivo;
        $headers = [
            "Content-Type" =>
                $r->boolean("mini") && $mensaje->miniatura
                    ? "image/jpeg"
                    : $mensaje->mime,
            "X-Content-Type-Options" => "nosniff",
            "Cache-Control" => "private, no-store",
            "Content-Security-Policy" => "sandbox; default-src 'none'",
        ];
        if (
            (str_starts_with($mensaje->mime, "image/") || str_starts_with($mensaje->mime,"audio/")) &&
            !$r->boolean("descargar")
        ) {
            return response()->file(
                Storage::disk("local")->path($path),
                $headers,
            );
        }

        return Storage::disk("local")->download(
            $mensaje->archivo,
            $mensaje->nombre,
            $headers,
        );
    }
}
