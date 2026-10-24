<?php
namespace Tests\Feature;
use App\Models\{User, Cyber, Cliente, CorreoServicio, JzChat, JzMensaje};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, Storage, Route};
use Illuminate\Support\Str;
use Tests\TestCase;
class CorreosGestionTest extends TestCase
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
    private function user($rol, $cy = null)
    {
        $s = Str::random(8);
        return User::create([
            "usuario" => "U" . $s,
            "name" => "Persona " . $s,
            "email" => $s . "@test.test",
            "password" => "Prueba12345678",
            "rol" => $rol,
            "activo" => true,
            "cyber_id" => $cy?->id,
        ]);
    }
    private function context()
    {
        $cy = Cyber::create(["codigo" => "CORREOA", "nombre" => "Cyber A"]);
        $otro = Cyber::create(["codigo" => "CORREOB", "nombre" => "Cyber B"]);
        $a = $this->user("cyber", $cy);
        $b = $this->user("cyber", $otro);
        $u = $this->user("cliente");
        $c = Cliente::create([
            "user_id" => $u->id,
            "primer_nombre" => "Nombre CV",
            "primer_apellido" => "Apellido",
        ]);
        $c->cybers()->attach($cy->id);
        return [$cy, $a, $b, $u, $c];
    }
    private function payload()
    {
        return [
            "pais" => "CR",
            "tipo_documento" => "cedula_cr",
            "numero_documento" => "102340567",
            "primer_nombre" => "Ana",
            "primer_apellido" => "Pérez",
            "correo" => "ana@example.com",
            "celular" => "+506 8888 8888",
            "proveedor" => "Otro",
            "clave_correo" => "  Clave & exacta 123  ",
            "confirmado" => "1",
        ];
    }
    public function test_correo_cifrado_ficha_reutilizada_y_password_no_se_filtra()
    {
        [$cy, $a, $b, $u, $c] = $this->context();
        $d = $this->payload();
        $this->actingAs($a)
            ->post(route("correos.guardar"), $d)
            ->assertRedirect();
        $reg = CorreoServicio::firstOrFail();
        $this->assertSame($d["clave_correo"], $reg->clave_correo);
        $this->assertStringNotContainsString(
            "Clave",
            DB::table("correo_servicios")->value("clave_correo"),
        );
        $this->assertArrayNotHasKey("clave_correo", $reg->toArray());
        $this->assertSame("Ana Pérez", $reg->cliente->nombreCompleto());
        $this->assertTrue($reg->cliente->cybers()->whereKey($cy->id)->exists());
        $this->get(route("correos.index"))
            ->assertOk()
            ->assertDontSee($d["clave_correo"]);
        $this->get(route("correos.ver", $reg))
            ->assertOk()
            ->assertDontSee($d["clave_correo"]);
        $d["correo"] = "segunda@example.com";
        $this->post(route("correos.guardar"), $d)->assertRedirect();
        $this->assertSame(
            $reg->cliente_id,
            CorreoServicio::latest("id")->first()->cliente_id,
        );
        $this->actingAs($b)->get(route("correos.ver", $reg))->assertForbidden();
        $this->get(route("correos.pdf", $reg))->assertForbidden();
        $this->actingAs($u)->get(route("correos.index"))->assertForbidden();
        $this->post(route("correos.guardar"), $d)->assertForbidden();
    }
    public function test_no_flash_password_y_valida_pais_documento_proveedor_y_duplicados()
    {
        [$cy, $a, $b] = $this->context();
        $d = $this->payload();
        $d["correo"] = "no-es-email";
        $this->actingAs($a)
            ->from(route("correos.index"))
            ->post(route("correos.guardar"), $d)
            ->assertSessionHasErrors("correo")
            ->assertSessionMissing("_old_input.clave_correo");
        $this->assertDatabaseCount("correo_servicios", 0);
        $d = $this->payload();
        $d["proveedor"] = "Gmail";
        $this->post(route("correos.guardar"), $d)->assertSessionHasErrors(
            "correo",
        );
        $d = $this->payload();
        $d["tipo_documento"] = "cedula";
        $this->post(route("correos.guardar"), $d)->assertSessionHasErrors(
            "numero_documento",
        );
        $d = $this->payload();
        $this->post(route("correos.guardar"), $d)->assertRedirect();
        $this->post(route("correos.guardar"), $d)->assertSessionHasErrors(
            "correo",
        );
        $this->assertDatabaseCount("correo_servicios", 1);
        $d["correo"] = "otra@example.com";
        $this->actingAs($b)
            ->post(route("correos.guardar"), $d)
            ->assertStatus(422);
        $this->assertDatabaseCount("correo_servicios", 1);
    }
    public function test_cajero_sin_permiso_no_puede_usar_modulo()
    {
        [$cy, $a] = $this->context();
        $a->forceFill(["permisos" => ["chat"]])->save();
        $this->actingAs($a)->get(route("correos.index"))->assertForbidden();
        $this->post(
            route("correos.guardar"),
            $this->payload(),
        )->assertForbidden();
    }
    public function test_chat_editar_ocultar_y_eliminar_con_sincronizacion_y_archivo_privado()
    {
        [$cy, $a, $b, $u, $c] = $this->context();
        Storage::fake("local");
        $chat = JzChat::create([
            "uno_id" => min($a->id, $u->id),
            "dos_id" => max($a->id, $u->id),
        ]);
        $m = JzMensaje::create([
            "chat_id" => $chat->id,
            "user_id" => $a->id,
            "clave" => Str::uuid(),
            "texto" => "Original",
            "archivo" => "chat/adjunto.pdf",
            "mime" => "application/pdf",
            "nombre" => "adjunto.pdf",
        ]);
        Storage::disk("local")->put("chat/adjunto.pdf", "pdf");
        $this->actingAs($u)
            ->patchJson(route("chat.mensaje.editar", $m), [
                "texto" => "Usurpado",
            ])
            ->assertForbidden();
        $this->deleteJson(route("chat.mensaje.eliminar", $m), [
            "para" => "todos",
        ])->assertForbidden();
        $this->actingAs($b)
            ->patchJson(route("chat.mensaje.editar", $m), ["texto" => "Ajeno"])
            ->assertForbidden();
        $this->actingAs($a)
            ->patchJson(route("chat.mensaje.editar", $m), [
                "texto" => "Editado seguro",
            ])
            ->assertOk();
        $this->actingAs($u)
            ->postJson(route("chat.sincronizar", $chat), ["ids" => [$m->id]])
            ->assertJsonPath("mensajes.0.texto", "Editado seguro")
            ->assertJsonPath("mensajes.0.editado", true);
        $this->deleteJson(route("chat.mensaje.eliminar", $m), [
            "para" => "mi",
        ])->assertOk();
        $this->get(route("chat.archivo", $m))->assertNotFound();
        $this->postJson(route("chat.sincronizar", $chat), ["ids" => [$m->id]])
            ->assertJsonPath("mensajes.0.oculto", true)
            ->assertJsonPath("mensajes.0.archivo", null);
        $this->actingAs($a)->get(route("chat.archivo", $m))->assertOk();
        $this->deleteJson(route("chat.mensaje.eliminar", $m), [
            "para" => "todos",
        ])->assertOk();
        $this->get(route("chat.archivo", $m))->assertNotFound();
        $this->assertNull($m->fresh()->texto);
        $this->assertNull($m->fresh()->archivo);
        $this->assertNotNull($m->fresh()->eliminado_en);
        $this->patchJson(route("chat.mensaje.editar", $m), [
            "texto" => "Revive",
        ])->assertStatus(409);
    }
    public function test_supervision_exclusiva_admin_auditada_sin_suplantar()
    {
        [$cy, $a, $b, $u] = $this->context();
        $admin = $this->user("admin");
        $chat = JzChat::create([
            "uno_id" => min($a->id, $u->id),
            "dos_id" => max($a->id, $u->id),
        ]);
        $m = JzMensaje::create([
            "chat_id" => $chat->id,
            "user_id" => $a->id,
            "clave" => Str::uuid(),
            "texto" => "Confidencial",
        ]);
        $this->actingAs($a)
            ->get(route("admin.chat.supervision.ver", $chat))
            ->assertForbidden();
        $this->actingAs($admin)
            ->get(route("admin.chat.supervision"))
            ->assertOk();
        $this->get(route("admin.chat.supervision.ver", $chat))
            ->assertOk()
            ->assertSee("Confidencial");
        $this->assertDatabaseHas("actividad", [
            "user_id" => $admin->id,
            "accion" => "chat.supervision",
        ]);
        $this->getJson(route("chat.historial", $chat))->assertForbidden();
        $this->postJson(route("chat.enviar", $chat), [
            "texto" => "Suplantar",
            "clave" => Str::uuid(),
        ])->assertForbidden();
    }
    public function test_identidad_y_foto_del_chat_provienen_del_cv()
    {
        [$cy, $a, $b, $u, $c] = $this->context();
        Storage::fake("local");
        Storage::disk("local")->put(
            "fotos/compartida.png",
            base64_decode(
                "iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=",
            ),
        );
        $c->update(["foto" => "fotos/compartida.png"]);
        $this->actingAs($a)
            ->getJson(route("chat.perfil.ver", $u))
            ->assertJsonPath("nombre", "Nombre CV Apellido")
            ->assertJsonPath(
                "foto",
                route("chat.perfil.foto", [
                    $u,
                    "v" => $c->fresh()->updated_at->timestamp,
                ]),
            );
        $this->get(route("chat.perfil.foto", $u))->assertOk();
        $this->actingAs($u)
            ->postJson(route("chat.perfil.guardar"), ["quitar_foto" => true])
            ->assertOk()
            ->assertJsonPath("foto", null);
        $this->assertNull($c->fresh()->foto);
    }
    public function test_pdf_escapa_datos_y_qr_incluye_datos_exactos_del_titular()
    {
        [$cy, $a, $b, $u, $c] = $this->context();
        $reg = CorreoServicio::create([
            "cliente_id" => $c->id,
            "cyber_id" => $cy->id,
            "pais" => "CR",
            "proveedor" => "Gmail",
            "correo" => "prueba@gmail.com",
            "celular" => "+506 88888888",
            "clave_correo" => "<Clave>&123",
        ]);
        $html = view("correos.pdf", [
            "correo" => $reg,
            "logo" => null,
            "qr" => "data:image/png;base64,iVBORw0KGgo=",
        ])->render();
        $this->assertStringContainsString("&lt;Clave&gt;&amp;123", $html);
        $qr = json_decode(
            $reg->contenidoQr(),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
        $this->assertSame("<Clave>&123", $qr["contrasena"]);
        $this->assertSame("Nombre CV Apellido", $qr["nombre"]);
        $this->assertSame("prueba@gmail.com", $qr["correo"]);
        $this->assertSame("+506 88888888", $qr["celular"]);
        $this->assertStringNotContainsString('data:"<Clave>', $html);
        $this->assertStringContainsString("size:letter", $html);
        $qa = getenv("MICV_QA_DIR");
        if ($qa && is_dir($qa)) {
            file_put_contents($qa . "/correo-preview.html", $html);
        }
    }
    public function test_foto_grande_se_normaliza_y_se_comparte_con_el_cv_y_chat_conserva_original()
    {
        $modules =
            getenv("CODEX_PRIMARY_RUNTIME_NODE_MODULES") ?:
            config("micv.pdf.node_modules");
        if (!is_file($modules . "/sharp/package.json")) {
            $this->markTestSkipped(
                "Instale las dependencias de Node (sharp) para la prueba de imágenes.",
            );
        }
        config([
            "micv.pdf.node_modules" => $modules,
            "chat.antivirus" => "/usr/bin/true",
        ]);
        [$cy, $a, $b, $u, $c] = $this->context();
        Storage::fake("local");
        $tmp = tempnam(sys_get_temp_dir(), "jz-foto-");
        $chunk = fn($tag, $data) => pack("N", strlen($data)) .
            $tag .
            $data .
            pack("N", crc32($tag . $data));
        $raw = "";
        for ($i = 0; $i < 2100; $i++) {
            $raw .= "\x00" . random_bytes(2100 * 3);
        }
        file_put_contents(
            $tmp,
            "\x89PNG\r\n\x1a\n" .
                $chunk("IHDR", pack("NNCCCCC", 2100, 2100, 8, 2, 0, 0, 0)) .
                $chunk("IDAT", gzcompress($raw, 1)) .
                $chunk("IEND", ""),
        );
        unset($raw);
        $this->assertGreaterThan(12 * 1024 * 1024, filesize($tmp));
        try {
            $file = new \Illuminate\Http\UploadedFile(
                $tmp,
                "foto-alta-calidad.png",
                "image/png",
                null,
                true,
            );
            $this->actingAs($u)
                ->post(
                    route("chat.perfil.guardar"),
                    ["foto" => $file],
                    ["Accept" => "application/json"],
                )
                ->assertOk()
                ->assertJsonPath("nombre", "Nombre CV Apellido");
            $foto = $c->fresh()->foto;
            $this->assertNotNull($foto);
            $this->assertNull($u->fresh()->chat_foto);
            $info = getimagesize(Storage::disk("local")->path($foto));
            $this->assertSame("image/jpeg", $info["mime"]);
            $this->assertSame(2100, $info[0]);
            $archivo = \App\Services\ChatArchivo::guardar($file);
            $this->assertSame(
                hash_file("sha256", $tmp),
                hash_file(
                    "sha256",
                    Storage::disk("local")->path($archivo["archivo"]),
                ),
            );
            $this->assertNotNull($archivo["miniatura"]);
            $this->actingAs($a)->get(route("chat.perfil.foto", $u))->assertOk();
            $this->actingAs($b)
                ->get(route("chat.perfil.foto", $u))
                ->assertForbidden();
        } finally {
            @unlink($tmp);
        }
    }
    public function test_las_lecturas_no_consumen_el_cupo_de_enviar_ni_perfil()
    {
        [$cy, $a, $b, $u] = $this->context();
        $this->actingAs($a);
        for ($i = 0; $i < 35; $i++) {
            $this->getJson(route("chat.lista"))->assertOk();
        }
        $chat = JzChat::create([
            "uno_id" => min($a->id, $u->id),
            "dos_id" => max($a->id, $u->id),
        ]);
        $this->postJson(route("chat.enviar", $chat), [
            "texto" => "Después de consultar",
            "clave" => \Illuminate\Support\Str::uuid(),
        ])->assertCreated();
        $this->postJson(route("chat.perfil.guardar"), [
            "info" => "Disponible",
        ])->assertOk();
    }
    public function test_admin_se_muestra_como_plataforma_y_el_personal_puede_actualizar_perfil()
    {
        [$cy, $a] = $this->context();
        $admin = $this->user("admin");
        $cfg = \App\Models\Configuracion::actual();
        $cfg->nombre_negocio = "Mi CV oficial";
        $cfg->save();
        $this->actingAs($a)
            ->getJson(route("chat.perfil.ver", $admin))
            ->assertOk()
            ->assertJsonPath("nombre", "Mi CV oficial")
            ->assertJsonPath("usuario", "Plataforma");
        $this->postJson(route("chat.perfil.guardar"), [
            "nombre" => "Agente del Cyber",
            "info" => "Disponible",
        ])
            ->assertOk()
            ->assertJsonPath("nombre", "Agente del Cyber");
    }
    public function test_audio_privado_validado_con_escaneo_y_reproduccion()
    {
        if (!is_executable("/usr/bin/ffmpeg")) {
            $this->markTestSkipped("Requiere ffmpeg y ffprobe.");
        }
        [$cy, $a, $b, $u] = $this->context();
        Storage::fake("local");
        config(["chat.antivirus" => "/usr/bin/true"]);
        $tmp = tempnam(sys_get_temp_dir(), "jz-audio");
        $process = new \Symfony\Component\Process\Process([
            "/usr/bin/ffmpeg",
            "-v",
            "error",
            "-f",
            "lavfi",
            "-i",
            "sine=frequency=440:duration=1",
            "-f",
            "wav",
            "-y",
            $tmp,
        ]);
        $process->mustRun();
        try {
            $chat = JzChat::create([
                "uno_id" => min($a->id, $u->id),
                "dos_id" => max($a->id, $u->id),
            ]);
            $file = new \Illuminate\Http\UploadedFile(
                $tmp,
                "nota.wav",
                "audio/wav",
                null,
                true,
            );
            $this->actingAs($a)
                ->post(
                    route("chat.enviar", $chat),
                    ["clave" => (string) Str::uuid(), "archivo" => $file],
                    ["Accept" => "application/json"],
                )
                ->assertCreated();
            $m = $chat->mensajes()->firstOrFail();
            $this->assertSame("audio/wav", $m->mime);
            $this->actingAs($u)
                ->get(route("chat.archivo", $m))
                ->assertOk()
                ->assertHeader("Content-Type", "audio/wav");
            $this->actingAs($b)
                ->get(route("chat.archivo", $m))
                ->assertForbidden();
        } finally {
            @unlink($tmp);
        }
    }
}
