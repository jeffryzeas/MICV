<?php

namespace Tests\Feature;

use App\Http\Middleware\AceptaTerminos;
use App\Http\Middleware\CorreoVerificado;
use App\Models\Cliente;
use App\Models\Configuracion;
use App\Models\Cyber;
use App\Models\JzMensaje;
use App\Models\User;
use App\Services\ChatAcceso;
use App\Services\Recaptcha;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class ChatJzTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(["app.key" => "base64:" . base64_encode(str_repeat("x", 32))]);
        $this->withoutMiddleware([
            CorreoVerificado::class,
            AceptaTerminos::class,
        ]);
    }

    private function usuario(
        string $rol = "cliente",
        ?Cyber $cy = null,
        ?array $permisos = null,
    ): User {
        $id = Str::random(8);

        return User::create([
            "usuario" => "U" . $id,
            "name" => "Usuario " . $id,
            "email" => $id . "@example.test",
            "password" => "ClaveLarga12345",
            "rol" => $rol,
            "cyber_id" => $cy?->id,
            "activo" => true,
            "permisos" => $permisos,
        ]);
    }

    private function contexto(): array
    {
        $a = Cyber::create(["codigo" => "CHAT-A", "nombre" => "Cyber A"]);
        $b = Cyber::create(["codigo" => "CHAT-B", "nombre" => "Cyber B"]);
        $sa = $this->usuario("cyber", $a);
        $sb = $this->usuario("cyber", $b);
        $cl = $this->usuario();
        $f = Cliente::create([
            "primer_nombre" => "Cliente",
            "primer_apellido" => "Prueba",
            "user_id" => $cl->id,
            "activo" => true,
        ]);
        $f->cybers()->attach($a->id);

        return [$a, $b, $sa, $sb, $cl, $f];
    }

    private function abrir(User $a, User $b): int
    {
        return $this->actingAs($a)
            ->postJson(route("chat.abrir"), ["user_id" => $b->id])
            ->assertOk()
            ->json("id");
    }

    public function test_cliente_solo_contacta_cyber_vinculado_y_admin(): void
    {
        [$a, $b, $sa, $sb, $cl] = $this->contexto();
        $this->assertTrue(ChatAcceso::puede($cl, $sa));
        $this->assertFalse(ChatAcceso::puede($cl, $sb));
        $this->actingAs($cl)
            ->postJson(route("chat.abrir"), ["user_id" => $sb->id])
            ->assertForbidden();
        $this->actingAs($cl)
            ->getJson(route("chat.contactos"))
            ->assertJsonFragment(["id" => $sa->id])
            ->assertJsonMissing(["id" => $sb->id]);
        $this->assertTrue(ChatAcceso::puede($cl, $this->usuario("admin")));
    }

    public function test_no_se_puede_entrar_a_historial_ni_archivos_ajenos(): void
    {
        [$a, $b, $sa, $sb, $cl] = $this->contexto();
        $id = $this->abrir($cl, $sa);
        $m = JzMensaje::create([
            "chat_id" => $id,
            "user_id" => $sa->id,
            "clave" => Str::uuid(),
            "texto" => "Privado",
            "archivo" => "chat/prueba.pdf",
            "nombre" => "prueba.pdf",
            "mime" => "application/pdf",
        ]);
        Storage::fake("local");
        Storage::disk("local")->put($m->archivo, "%PDF-1.4");
        foreach ([$sb, $this->usuario("admin")] as $otro) {
            $this->actingAs($otro)
                ->getJson(route("chat.historial", $id))
                ->assertForbidden();
            $this->actingAs($otro)
                ->getJson(route("chat.archivo", $m))
                ->assertForbidden();
        }
    }

    public function test_mensaje_es_cifrado_en_bd_y_reintento_no_duplica(): void
    {
        [$a, $b, $sa, $sb, $cl] = $this->contexto();
        $id = $this->abrir($cl, $sa);
        $d = [
            "clave" => Str::uuid()->toString(),
            "texto" => "<script>alert(1)</script>",
            "sistema" => true,
        ];
        $this->actingAs($cl)
            ->postJson(route("chat.enviar", $id), $d)
            ->assertCreated();
        $this->postJson(route("chat.enviar", $id), $d)->assertOk();
        $this->assertDatabaseCount("jz_mensajes", 1);
        $m = JzMensaje::first();
        $this->assertSame($d["texto"], $m->texto);
        $this->assertFalse($m->sistema);
        $this->assertNotSame(
            $d["texto"],
            DB::table("jz_mensajes")->value("texto"),
        );
        $this->actingAs($sa)
            ->postJson(route("chat.leer", $id), ["hasta" => PHP_INT_MAX])
            ->assertOk();
        $this->getJson(route("chat.lista"))->assertJsonPath("total", 0);
    }

    public function test_desvincular_cliente_revoca_chat_existente(): void
    {
        [$a, $b, $sa, $sb, $cl, $f] = $this->contexto();
        $id = $this->abrir($cl, $sa);
        $f->cybers()->detach($a->id);
        $this->actingAs($cl)
            ->getJson(route("chat.historial", $id))
            ->assertForbidden();
    }

    public function test_permiso_chat_y_usuario_activo_se_validan_en_servidor(): void
    {
        [$a, $b, $sa, $sb, $cl] = $this->contexto();
        $sa->update(["permisos" => []]);
        $this->actingAs($sa)->getJson(route("chat.lista"))->assertForbidden();
        $sa->update(["permisos" => null, "activo" => false]);
        $this->actingAs($cl)
            ->postJson(route("chat.abrir"), ["user_id" => $sa->id])
            ->assertForbidden();
    }

    public function test_archivos_de_codigo_y_antivirus_ausente_se_rechazan(): void
    {
        [$a, $b, $sa, $sb, $cl] = $this->contexto();
        $id = $this->abrir($cl, $sa);
        $this->actingAs($cl)
            ->postJson(route("chat.enviar", $id), [
                "clave" => Str::uuid(),
                "archivo" => UploadedFile::fake()->createWithContent(
                    "peligro.php",
                    "<?php echo 1;",
                ),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors("archivo");
        config(["chat.antivirus" => "/no-existe/antivirus"]);
        $this->postJson(route("chat.enviar", $id), [
            "clave" => Str::uuid(),
            "archivo" => UploadedFile::fake()->createWithContent(
                "normal.pdf",
                "%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\n%%EOF",
            ),
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors("archivo");
        $this->assertDatabaseCount("jz_mensajes", 0);
    }

    public function test_qr_vence_sin_cron_y_cierre_es_explicito(): void
    {
        [$a, $b, $sa] = $this->contexto();
        $a->update([
            "autoservicio" => true,
            "autoservicio_hasta" => now()->subMinute(),
        ]);
        $this->get(route("autoservicio.formulario", $a->codigo))->assertStatus(
            503,
        );
        $this->post(
            route("autoservicio.guardar", $a->codigo),
            [],
        )->assertStatus(503);
        $this->actingAs($sa)
            ->post(route("autoservicio.alternar"), [
                "activar" => 1,
                "horas" => 2,
            ])
            ->assertRedirect();
        $this->assertTrue($a->fresh()->qrAbierto());
        $this->post(route("autoservicio.alternar"), [
            "activar" => 0,
        ])->assertRedirect();
        $this->post(route("autoservicio.alternar"), [
            "activar" => 0,
        ])->assertRedirect();
        $this->assertFalse($a->fresh()->qrAbierto());
    }

    public function test_recaptcha_publico_falla_cerrado_y_valida_accion_y_host(): void
    {
        $cfg = Configuracion::actual();
        $cfg->update(["recaptcha_activo" => false]);
        $this->assertNotNull(Recaptcha::verificar("t", "autoservicio", true));
        $cfg->update([
            "recaptcha_activo" => true,
            "recaptcha_site_key" => "site",
            "recaptcha_secret_key" => "secret",
            "recaptcha_umbral" => 0.5,
        ]);
        config(["app.url" => "https://micv.example"]);
        Http::fake([
            "*" => Http::sequence()
                ->push([
                    "success" => true,
                    "score" => 0.9,
                    "action" => "autoservicio",
                    "hostname" => "micv.example",
                ])
                ->push([
                    "success" => true,
                    "score" => 0.9,
                    "action" => "login",
                    "hostname" => "micv.example",
                ])
                ->push([
                    "success" => true,
                    "score" => 0.9,
                    "action" => "autoservicio",
                    "hostname" => "otro.example",
                ]),
        ]);
        $this->assertNull(Recaptcha::verificar("t", "autoservicio", true));
        $this->assertNotNull(Recaptcha::verificar("t", "autoservicio", true));
        $this->assertNotNull(Recaptcha::verificar("t", "autoservicio", true));
    }

    public function test_invitacion_no_reemplaza_cuenta_y_solo_se_consume_una_vez(): void
    {
        [$a, $b, $sa, $sb, $cl, $f] = $this->contexto();
        $this->actingAs($sa)
            ->post(route("clientes.acceso.crear", $f), [
                "email" => "otro@example.test",
            ])
            ->assertSessionHasErrors("email");
        $f->update(["user_id" => null]);
        $this->post(route("clientes.acceso.crear", $f), [
            "email" => "nuevo@example.test",
        ])->assertSessionHas("invitacion");
        $token = basename(session("invitacion"));
        $this->post(route("acceso.guardar", $token), [
            "password" => "NuevaClave12345",
            "password_confirmation" => "NuevaClave12345",
        ])->assertRedirect(route("verificar.mostrar"));
        $this->assertDatabaseCount("jz_invitaciones", 0);
        $this->post(route("acceso.guardar", $token), [
            "password" => "OtraClave12345",
            "password_confirmation" => "OtraClave12345",
        ])->assertStatus(410);
    }

    public function test_avisos_solo_admin_y_sin_duplicados(): void
    {
        [$a, $b, $sa, $sb, $cl] = $this->contexto();
        $d = [
            "clave" => Str::uuid()->toString(),
            "texto" => "Mantenimiento",
            "rol" => "cliente",
        ];
        $this->actingAs($sa)
            ->postJson(route("admin.chat.avisos.enviar"), $d)
            ->assertForbidden();
        $admin = $this->usuario("admin");
        $this->actingAs($admin)
            ->post(route("admin.chat.avisos.enviar"), $d)
            ->assertRedirect();
        $this->post(route("admin.chat.avisos.enviar"), $d)->assertRedirect();
        $this->assertDatabaseCount("jz_mensajes", 1);
        $this->assertTrue(JzMensaje::first()->sistema);
    }

    public function test_acceso_solo_con_password_sin_rutas_passkeys():void {
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('passkey.entrar'));
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('passkeys.index'));
    }

    public function test_pantallas_nuevas_renderizan_en_el_sistema_real(): void
    {
        [$a, $b, $sa, $sb, $cl, $f] = $this->contexto();
        foreach (
            [
                "chat.index",
                "documentos.index",
                "tarjetas.index",
                "autoservicio.panel",
                "correos.index",
            ]
            as $ruta
        ) {
            $this->actingAs($sa)->get(route($ruta))->assertOk();
        }
        $this->get(route("clientes.acceso", $f))->assertOk();
        $admin = $this->usuario("admin");
        $this->actingAs($admin)
            ->get(route("admin.usuarios.index", ["cyber_id" => $a->id]))
            ->assertOk()
            ->assertSee($cl->email)
            ->assertSee($sa->email)
            ->assertDontSee($sb->email);
        $this->get(route("admin.chat.avisos"))->assertOk();
    }
    public function test_historial_incremental_no_pierde_mensajes_en_una_rafaga(): void
    {
        [$a, $b, $sa, $sb, $cl] = $this->contexto();
        $id = $this->abrir($cl, $sa);
        for ($i = 1; $i <= 65; $i++) {
            JzMensaje::create([
                "chat_id" => $id,
                "user_id" => $sa->id,
                "clave" => Str::uuid(),
                "texto" => "Mensaje " . $i,
            ]);
        }
        $primero = $this->actingAs($cl)
            ->getJson(route("chat.historial", [$id, "despues" => 0]))
            ->assertOk()
            ->json("mensajes");
        $this->assertCount(60, $primero);
        $this->assertSame("Mensaje 1", $primero[0]["texto"]);
        $this->getJson(
            route("chat.historial", [$id, "despues" => $primero[59]["id"]]),
        )
            ->assertOk()
            ->assertJsonCount(5, "mensajes")
            ->assertJsonPath("mensajes.4.texto", "Mensaje 65");
    }

    public function test_pdf_y_office_validos_y_contenido_activo_con_scanner_simulado(): void
    {
        // Se simula únicamente un resultado limpio del escáner. No prueba firmas de ClamAV.
        if (PHP_OS_FAMILY === "Windows") {
            $this->markTestSkipped(
                "Este fixture de escáner requiere /usr/bin/true.",
            );
        }
        config(["chat.antivirus" => "/usr/bin/true"]);
        Storage::fake("local");
        [$a, $b, $sa, $sb, $cl] = $this->contexto();
        $id = $this->abrir($cl, $sa);
        $this->actingAs($sa)
            ->postJson(route("chat.enviar", $id), [
                "clave" => Str::uuid(),
                "archivo" => UploadedFile::fake()->createWithContent(
                    "Trabajo.pdf",
                    "%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\n%%EOF",
                ),
            ])
            ->assertCreated();
        $m = JzMensaje::first();
        $this->actingAs($cl)
            ->get(route("chat.archivo", $m))
            ->assertOk()
            ->assertDownload("Trabajo.pdf");
        $this->postJson(route("chat.enviar", $id), [
            "clave" => Str::uuid(),
            "archivo" => UploadedFile::fake()->createWithContent(
                "Activo.pdf",
                "%PDF-1.4\n/JavaScript (alert(1))\n%%EOF",
            ),
        ])->assertUnprocessable();
        $tmp = tempnam(sys_get_temp_dir(), "jz-test-");
        $zip = new \ZipArchive();
        $zip->open($tmp, \ZipArchive::OVERWRITE);
        $zip->addFromString("[Content_Types].xml", "<Types/>");
        $zip->addFromString("word/document.xml", "<document/>");
        $zip->close();
        try {
            $this->postJson(route("chat.enviar", $id), [
                "clave" => Str::uuid(),
                "archivo" => new UploadedFile(
                    $tmp,
                    "Trabajo.docx",
                    "application/vnd.openxmlformats-officedocument.wordprocessingml.document",
                    null,
                    true,
                ),
            ])->assertCreated();
            $zip->open($tmp);
            $zip->addFromString("word/vbaProject.bin", "macro");
            $zip->close();
            $this->postJson(route("chat.enviar", $id), [
                "clave" => Str::uuid(),
                "archivo" => new UploadedFile(
                    $tmp,
                    "Macro.docx",
                    "application/vnd.openxmlformats-officedocument.wordprocessingml.document",
                    null,
                    true,
                ),
            ])->assertUnprocessable();
        } finally {
            @unlink($tmp);
        }
        $this->assertDatabaseCount("jz_mensajes", 2);
    }

    public function test_activar_una_cuenta_expirada_no_cambia_contrasena(): void
    {
        [$a, $b, $sa, $sb, $cl] = $this->contexto();
        $token = Str::random(64);
        $anterior = $cl->password;
        DB::table("jz_invitaciones")->insert([
            "user_id" => $cl->id,
            "token" => hash("sha256", $token),
            "vence" => now()->subMinute(),
            "created_at" => now(),
            "updated_at" => now(),
        ]);
        $this->post(route("acceso.guardar", $token), [
            "password" => "NuevaClave12345",
            "password_confirmation" => "NuevaClave12345",
        ])->assertStatus(410);
        $this->assertSame($anterior, $cl->fresh()->password);
    }
}
