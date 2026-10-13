<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Actividad;
use App\Models\Cyber;
use App\Models\JzChat;
use App\Models\JzMensaje;
use App\Models\User;
use App\Services\ChatAcceso;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ChatAvisoController extends Controller
{
    private function destino(Request $r)
    {
        $u = $r->user();
        $d = $r->validate([
            "rol" => "nullable|in:admin,cyber,cliente",
            "cyber" => "nullable|integer|exists:cybers,id",
        ]);

        return ChatAcceso::contactos($u)
            ->when($d["rol"] ?? null, fn($q, $rol) => $q->where("rol", $rol))
            ->when(
                $d["cyber"] ?? null,
                fn($q, $id) => $q->where(
                    fn($w) => $w
                        ->where("cyber_id", $id)
                        ->orWhereHas(
                            "cliente.cybers",
                            fn($c) => $c->where("cybers.id", $id),
                        ),
                ),
            );
    }

    public function index(Request $r)
    {
        $destinatarios = $this->destino($r)
            ->get()
            ->filter(fn($u) => ChatAcceso::habilitado($u));

        return view("chat.avisos", [
            "cybers" => Cyber::orderBy("nombre")->get(),
            "cantidad" => $destinatarios->count(),
            "clave" => Str::uuid()->toString(),
        ]);
    }

    public function enviar(Request $r)
    {
        $d = $r->validate([
            "texto" => "required|string|max:6000",
            "clave" => "required|uuid",
        ]);
        $dest = $this->destino($r)
            ->get()
            ->filter(fn($u) => ChatAcceso::habilitado($u));
        abort_if(
            $dest->count() > 1000,
            422,
            "Seleccione un Cyber o un tipo de usuario para enviar a un máximo de 1000 personas.",
        );
        DB::transaction(function () use ($r, $d, $dest) {
            User::whereKey($r->user()->id)->lockForUpdate()->firstOrFail();
            if (
                DB::table("jz_avisos")
                    ->where("user_id", $r->user()->id)
                    ->where("clave", $d["clave"])
                    ->exists()
            ) {
                return;
            }
            foreach ($dest as $u) {
                $ids = [$u->id, $r->user()->id];
                sort($ids);
                $c = JzChat::firstOrCreate([
                    "uno_id" => $ids[0],
                    "dos_id" => $ids[1],
                ]);
                JzMensaje::create([
                    "chat_id" => $c->id,
                    "user_id" => $r->user()->id,
                    "clave" => Str::uuid()->toString(),
                    "texto" => $d["texto"],
                    "sistema" => true,
                ]);
                $c->touch();
            }
            DB::table("jz_avisos")->insert([
                "user_id" => $r->user()->id,
                "clave" => $d["clave"],
                "destinatarios" => $dest->count(),
                "created_at" => now(),
                "updated_at" => now(),
            ]);
        });
        Actividad::registrar(
            "chat_aviso_sistema",
            "Destinatarios: " . $dest->count(),
        );

        return back()->with(
            "mensaje",
            "Aviso publicado en las conversaciones de Sistema JZ.",
        );
    }
}
