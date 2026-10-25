<?php
namespace Tests\Feature;
use App\Models\{User, Cyber, Cliente, JzChat, JzMensaje};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;
class ChatPerfilesTest extends TestCase
{
    use RefreshDatabase;
    protected function setUp(): void
    {
        parent::setUp();
        config(["app.key" => "base64:" . base64_encode(str_repeat("x", 32))]);
        $this->withoutMiddleware([
            \App\Http\Middleware\CorreoVerificado::class,
            \App\Http\Middleware\AceptaTerminos::class,
        ]);
    }
    private function user(string $rol, Cyber $cy = null): User
    {
        $n = Str::random(8);
        return User::create([
            "usuario" => "U" . $n,
            "name" => "Nombre " . $n,
            "email" => $n . "@example.test",
            "password" => "UnaClave123456",
            "rol" => $rol,
            "activo" => true,
            "cyber_id" => $cy?->id,
        ]);
    }
    private function context(): array
    {
        $a = Cyber::create(["codigo" => "PERFIL-A", "nombre" => "Cyber A"]);
        $b = Cyber::create(["codigo" => "PERFIL-B", "nombre" => "Cyber B"]);
        $sa = $this->user("cyber", $a);
        $sb = $this->user("cyber", $b);
        $u = $this->user("cliente");
        $c = Cliente::create([
            "primer_nombre" => "Cliente",
            "primer_apellido" => "Perfil",
            "user_id" => $u->id,
        ]);
        $c->cybers()->attach($a->id);
        return [$a, $b, $sa, $sb, $u];
    }
    public function test_perfil_y_foto_solo_son_visibles_para_contactos_autorizados(): void
    {
        [$a, $b, $sa, $sb, $u] = $this->context();
        Storage::fake("local");
        Storage::disk("local")->put("productos/perfil.jpg", "imagen de prueba");
        $sa->forceFill([
            "chat_foto" => "productos/perfil.jpg",
            "chat_info" => "Atención del Cyber",
        ])->save();
        $this->actingAs($u)
            ->getJson(route("chat.perfil.ver", $sa))
            ->assertOk()
            ->assertJsonPath("info", "Atención del Cyber")
            ->assertJsonMissingPath("email")
            ->assertJsonMissingPath("password");
        $this->get(route("chat.perfil.foto", $sa))->assertOk();
        $this->actingAs($sb)
            ->getJson(route("chat.perfil.ver", $sa))
            ->assertForbidden();
        $this->getJson(route("chat.perfil.foto", $sa))->assertForbidden();
    }
    public function test_editar_perfil_no_permite_cambiar_rol_cyber_ni_nombre_del_cv(): void
    {
        [$a, $b, $sa, $sb, $u] = $this->context();
        $original = $u->cliente->nombreCompleto();
        $this->actingAs($u)
            ->postJson(route("chat.perfil.guardar"), [
                "nombre" => "Nombre falso",
                "info" => "Disponible para atención",
                "rol" => "admin",
                "cyber_id" => $b->id,
            ])
            ->assertOk()
            ->assertJsonPath("nombre", $original);
        $this->assertSame("cliente", $u->fresh()->rol);
        $this->assertNull($u->fresh()->cyber_id);
        $this->assertSame("Disponible para atención", $u->fresh()->chat_info);
        $this->postJson(route("chat.perfil.guardar"), ["info" => ""])
            ->assertOk()
            ->assertJsonPath("info", "");
        $this->actingAs($sa)
            ->postJson(route("chat.perfil.guardar"), [
                "nombre" => "Nombre del encargado",
                "info" => "Nuevo perfil",
            ])
            ->assertOk()
            ->assertJsonPath("nombre", "Nombre del encargado");
    }
    public function test_archivos_del_panel_de_contacto_respetan_miembros_y_paginacion(): void
    {
        [$a, $b, $sa, $sb, $u] = $this->context();
        $ids = [$sa->id, $u->id];
        sort($ids);
        $chat = JzChat::create(["uno_id" => $ids[0], "dos_id" => $ids[1]]);
        for ($i = 0; $i < 31; $i++) {
            JzMensaje::create([
                "chat_id" => $chat->id,
                "user_id" => $sa->id,
                "clave" => Str::uuid(),
                "nombre" => "Archivo " . $i . ".pdf",
                "archivo" => "chat/" . $i . ".pdf",
                "mime" => "application/pdf",
            ]);
        }
        $r = $this->actingAs($u)
            ->getJson(route("chat.archivos", $chat))
            ->assertOk()
            ->assertJsonCount(30, "archivos")
            ->assertJsonPath("mas", true);
        $this->getJson(
            route("chat.archivos", [$chat, "antes" => $r->json("antes")]),
        )
            ->assertOk()
            ->assertJsonCount(1, "archivos")
            ->assertJsonPath("mas", false);
        $this->actingAs($sb)
            ->getJson(route("chat.archivos", $chat))
            ->assertForbidden();
    }
    public function test_boton_editor_e_iconos_se_renderizan_desde_laravel(): void
    {
        [$a, $b, $sa] = $this->context();
        $this->actingAs($sa)
            ->get(route("documentos.index"))
            ->assertOk()
            ->assertSee('id="doc-upload"', false)
            ->assertSee("Subir fotos")
            ->assertSee("<svg", false)
            ->assertDontSee("↶");
        $this->get(route("chat.index"))
            ->assertOk()
            ->assertSee('id="jzc-me"', false)
            ->assertSee('id="jzc-who"', false)
            ->assertSee('id="jzc-sheet"', false)
            ->assertSee('id="jzc-info-panel"', false)
            ->assertDontSee("jzc-main");
    }
}
