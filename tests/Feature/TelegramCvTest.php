<?php

namespace Tests\Feature;

use App\Http\Middleware\AceptaTerminos;
use App\Http\Middleware\CorreoVerificado;
use App\Jobs\EnviarTelegramCv;
use App\Models\Cliente;
use App\Models\TelegramCvCuenta as Cuenta;
use App\Models\TelegramCvEnvio as Envio;
use App\Models\User;
use App\Services\PostulacionPdf;
use App\Services\TelegramCvCliente;
use App\Services\TelegramCvError;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class TelegramCvTest extends TestCase
{
    use RefreshDatabase;

    private User $u;

    private Cliente $c;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Queue::fake();
        $this->withoutMiddleware([CorreoVerificado::class, AceptaTerminos::class]);
        $this->u = $this->usuario();
        $this->actingAs($this->u);
        $this->c = Cliente::create(['user_id' => $this->u->id, 'primer_nombre' => 'Ana', 'primer_apellido' => 'Pérez', 'activo' => true]);
        $this->mock(PostulacionPdf::class, fn ($m) => $m->shouldReceive('generar')->andReturn('%PDF-1.4 copia original'));
    }

    private function usuario(string $rol = 'admin'): User
    {
        $id = Str::random(8);

        return User::create(['usuario' => 'TC'.$id, 'name' => 'Administrador '.$id, 'email' => $id.'@example.test', 'rol' => $rol, 'activo' => true, 'password' => 'Prueba12345678']);
    }

    private function conectar(): Cuenta
    {
        $c = Cuenta::para($this->u);
        $c->update(['api_id' => 12345, 'api_hash' => str_repeat('a', 32), 'telefono' => '+50688887777', 'sesion' => 'SESION-FICTICIA-SECRETA', 'estado' => 'conectada', 'telegram_id' => '8476238020', 'nombre' => 'Jeffry', 'conectada_en' => now()]);

        return $c->fresh();
    }

    public function test_formulario_comparte_galeria_visual_con_cv_y_no_muestra_secretos(): void
    {
        $this->conectar();
        $this->get(route('admin.telegram-cv.crear'))->assertOk()->assertSee('Vista previa del CV')->assertSee('Nombre para su agenda')->assertDontSee('SESION-FICTICIA-SECRETA', false);
    }

    private function payload(string $tipo = 'cv'): array
    {
        return ['telefono' => '+50588887777', 'contacto' => 'Empresa de prueba', 'tipo' => $tipo, 'mensaje' => 'Saludos cordiales. Le comparto mi CV.', 'cliente_id' => $this->c->id, 'diseno' => 'pro', 'paleta' => 'marino', 'foto' => '1', 'idioma' => 'es', 'confirmar_contacto' => '1'];
    }

    private function preparar(string $tipo = 'cv'): Envio
    {
        $this->conectar();
        $this->mock(TelegramCvCliente::class, fn ($m) => $m->shouldReceive('operar')->once()->withArgs(fn ($c, $accion, $d) => $accion === 'resolver' && $d['destino'] === '+50588887777')->andReturn(['id' => '7000000000001', 'access_hash' => '-9000000000000000000', 'nombre' => 'Empresa real']));
        $this->post(route('admin.telegram-cv.preparar'), $this->payload($tipo))->assertSessionHasNoErrors()->assertRedirect();

        return Envio::firstOrFail();
    }

    public function test_administradores_comparten_cuenta_del_sistema_y_cliente_no_ve_configuracion_ni_historial_ajeno(): void
    {
        $e = $this->preparar();
        $otro = $this->usuario();
        $this->actingAs($otro)->get(route('admin.telegram-cv.ver', $e))->assertOk();
        $this->assertSame(Cuenta::para($this->u)->id, Cuenta::para($otro)->id);
        $this->get(route('admin.telegram-cv.index'))->assertOk()->assertSee('Empresa real');
        $this->actingAs($this->usuario('cliente'))->get(route('admin.telegram-cv.cuenta'))->assertForbidden();
        $this->get(route('admin.telegram-cv.ver', $e))->assertNotFound();
        $this->get(route('admin.telegram-cv.pdf', $e))->assertNotFound();
        $this->post(route('admin.telegram-cv.confirmar', $e), ['confirmar' => 1])->assertNotFound();
        Queue::assertNothingPushed();
    }

    public function test_borrador_muestra_identidad_real_y_pdf_inmutable_sin_enviar(): void
    {
        $e = $this->preparar();
        $this->assertSame('borrador', $e->estado);
        $this->assertSame('Empresa real', $e->destinatario);
        $this->assertSame('-9000000000000000000', $e->peer['access_hash']);
        $this->assertNotSame(json_encode($e->peer), DB::table('telegram_cv_envios')->value('peer'));
        $this->c->update(['primer_nombre' => 'Cambiado']);
        $this->get(route('admin.telegram-cv.ver', $e))->assertOk()->assertSee('Ana Pérez')->assertSee('Confirmar y enviar');
        $this->get(route('admin.telegram-cv.pdf', $e))->assertOk()->assertHeader('Content-Disposition');
        $this->assertSame('%PDF-1.4 copia original', Storage::disk('local')->get($e->cv_ruta));
        $this->assertSame(hash('sha256', '%PDF-1.4 copia original'), $e->cv_sha256);
        Queue::assertNothingPushed();
    }

    public function test_mensaje_sin_cv_no_genera_archivos_y_confirmar_es_idempotente(): void
    {
        $e = $this->preparar('mensaje');
        $this->assertNull($e->cv_ruta);
        $this->assertSame([], Storage::disk('local')->allFiles());
        $this->post(route('admin.telegram-cv.confirmar', $e), ['confirmar' => 1])->assertRedirect();
        $this->post(route('admin.telegram-cv.confirmar', $e), ['confirmar' => 1])->assertRedirect();
        Queue::assertPushed(EnviarTelegramCv::class, 1);
        $this->assertSame('en_cola', $e->fresh()->estado);
        $this->get(route('admin.telegram-cv.pdf', $e))->assertNotFound();
    }

    public function test_validaciones_de_numero_consentimiento_limite_y_cuenta(): void
    {
        $this->post(route('admin.telegram-cv.preparar'), $this->payload())->assertSessionHasErrors('cuenta');
        $this->conectar();
        $this->mock(TelegramCvCliente::class, fn ($m) => $m->shouldNotReceive('operar'));
        $this->post(route('admin.telegram-cv.preparar'), array_replace($this->payload(), ['telefono' => '88887777']))->assertSessionHasErrors('telefono');
        $this->post(route('admin.telegram-cv.preparar'), array_replace($this->payload(), ['confirmar_contacto' => 0]))->assertSessionHasErrors('confirmar_contacto');
        $this->post(route('admin.telegram-cv.preparar'), array_replace($this->payload(), ['mensaje' => str_repeat('😀', 513)]))->assertSessionHasErrors('mensaje');
        $this->assertSame(0, Envio::count());
    }

    public function test_no_se_sustituye_un_destino_que_telegram_no_puede_localizar(): void
    {
        $this->conectar();
        $this->mock(TelegramCvCliente::class, fn ($m) => $m->shouldReceive('operar')->once()->andThrow(new TelegramCvError('destino')));
        $this->post(route('admin.telegram-cv.preparar'), $this->payload())->assertSessionHasErrors('telefono');
        $this->assertSame(0, Envio::count());
        $this->assertSame([], Storage::disk('local')->allFiles());
        Queue::assertNothingPushed();
    }

    public function test_trabajador_envia_version_confirmada_una_vez_y_no_contenido_nuevo(): void
    {
        $e = $this->preparar();
        $e->update(['estado' => 'en_cola']);
        $this->c->update(['primer_nombre' => 'Otro']);
        $m = $this->mock(TelegramCvCliente::class, fn ($m) => $m->shouldReceive('operar')->once()->withArgs(fn ($c, $a, $d) => $a === 'enviar' && $d['peer']['id'] === '7000000000001' && $d['random_id'] === $e->random_id && $d['archivo_sha256'] === $e->cv_sha256 && $d['mensaje'] === $e->mensaje)->andReturn(['mensaje_id' => '123']));
        $j = new EnviarTelegramCv($e->id);
        $j->handle($m);
        $j->handle($m);
        $this->assertSame('enviado', $e->fresh()->estado);
        $this->assertSame('123', $e->fresh()->mensaje_id);
    }

    public function test_destinatario_interno_distinto_del_revisado_no_se_envia(): void
    {
        $e = $this->preparar('mensaje');
        $e->update(['estado' => 'en_cola', 'peer' => ['id' => '999999999', 'access_hash' => '12345']]);
        $m = $this->mock(TelegramCvCliente::class, fn ($m) => $m->shouldNotReceive('operar'));
        (new EnviarTelegramCv($e->id))->handle($m);
        $this->assertSame('fallido', $e->fresh()->estado);
        $this->assertNull($e->fresh()->mensaje_id);
    }

    public function test_archivo_alterado_y_cambio_de_cuenta_impiden_envio(): void
    {
        $e = $this->preparar();
        $e->update(['estado' => 'en_cola']);
        Storage::disk('local')->put($e->cv_ruta, '%PDF- alterado');
        $m = $this->mock(TelegramCvCliente::class, fn ($m) => $m->shouldNotReceive('operar'));
        (new EnviarTelegramCv($e->id))->handle($m);
        $this->assertSame('fallido', $e->fresh()->estado);
        $e->update(['estado' => 'en_cola']);
        $e->cuenta->update(['revision' => (string) Str::uuid()]);
        (new EnviarTelegramCv($e->id))->handle($m);
        $this->assertSame('fallido', $e->fresh()->estado);
    }

    public function test_resultado_incierto_no_se_repite_y_rechazo_conocido_no_se_presenta_como_enviado(): void
    {
        $e = $this->preparar('mensaje');
        $e->update(['estado' => 'en_cola']);
        $m = $this->mock(TelegramCvCliente::class, fn ($m) => $m->shouldReceive('operar')->once()->andThrow(new TelegramCvError));
        $j = new EnviarTelegramCv($e->id);
        $j->handle($m);
        $j->handle($m);
        $this->assertSame('incierto', $e->fresh()->estado);
    }

    public function test_rechazo_de_privacidad_es_fallido_sin_afirmar_envio(): void
    {
        $e = $this->preparar('mensaje');
        $e->update(['estado' => 'en_cola']);
        $m = new class extends TelegramCvCliente
        {
            public function operar(Cuenta $c, string $a, array $d = []): array
            {
                throw new TelegramCvError('privacidad');
            }
        };
        (new EnviarTelegramCv($e->id))->handle($m);
        $this->assertSame('fallido', $e->fresh()->estado);
        $this->assertNull($e->fresh()->mensaje_id);
    }

    public function test_error_de_conexion_durante_envio_se_conserva_como_incierto(): void
    {
        $e = $this->preparar('mensaje');
        $e->update(['estado' => 'en_cola']);
        $m = new class extends TelegramCvCliente
        {
            public function operar(Cuenta $c, string $a, array $d = []): array
            {
                throw new TelegramCvError('conexion', 0, 'CONEXION');
            }
        };
        $j = new EnviarTelegramCv($e->id);
        $j->handle($m);
        $j->handle($m);
        $this->assertSame('incierto', $e->fresh()->estado);
        $this->assertNull($e->fresh()->mensaje_id);
    }

    public function test_cifrado_y_campos_sensibles_no_aparecen_en_html_json_ni_old_input(): void
    {
        $c = $this->conectar();
        $this->assertNotSame(str_repeat('a', 32), DB::table('telegram_cv_cuentas')->value('api_hash'));
        $this->assertNotSame($c->sesion, DB::table('telegram_cv_cuentas')->value('sesion'));
        foreach (['api_hash', 'sesion', 'codigo_hash'] as $k) {
            $this->assertArrayNotHasKey($k, $c->toArray());
        }$this->get(route('admin.telegram-cv.cuenta'))->assertOk()->assertDontSee(str_repeat('a', 32), false)->assertDontSee('SESION-FICTICIA-SECRETA', false);
        $this->post(route('admin.telegram-cv.codigo'), ['tg_api_hash' => 'SECRETO', 'tg_codigo' => '12345', 'tg_clave' => 'CLAVE'])->assertSessionHasErrors();
        foreach (['tg_api_hash', 'tg_codigo', 'tg_clave'] as $k) {
            $this->assertArrayNotHasKey($k, session('_old_input', []));
        }
    }

    public function test_conexion_codigo_y_dos_pasos_no_envia_mensajes_y_no_guarda_clave(): void
    {
        $m = $this->mock(TelegramCvCliente::class);
        $m->shouldReceive('operar')->once()->withArgs(fn ($c, $a) => $a === 'codigo')->andReturnUsing(function ($c) {
            $c->update(['sesion' => 'S']);

            return ['v' => 1, 'ok' => true, 'codigo_hash' => 'HASH-FICTICIO', 'sesion' => 'S'];
        });
        $this->post(route('admin.telegram-cv.codigo'), ['tg_api_id' => 12345, 'tg_api_hash' => str_repeat('a', 32), 'tg_telefono' => '+50688887777', 'confirmar' => 1])->assertSessionHasNoErrors();
        $c = Cuenta::para($this->u);
        $this->assertSame('codigo', $c->estado);
        $this->assertSame('HASH-FICTICIO', $c->codigo_hash);
        $this->assertNotSame('HASH-FICTICIO', DB::table('telegram_cv_cuentas')->value('codigo_hash'));
        $m->shouldReceive('operar')->once()->withArgs(fn ($c, $a, $d) => $a === 'verificar' && $d['codigo'] === '12345')->andReturn(['estado' => 'clave']);
        $this->post(route('admin.telegram-cv.verificar'), ['tg_codigo' => '12345'])->assertSessionHasNoErrors();
        $this->assertSame('clave', $c->fresh()->estado);
        $m->shouldReceive('operar')->once()->withArgs(fn ($c, $a, $d) => $a === 'clave' && $d['clave'] === 'CLAVE-PRIVADA')->andReturn(['estado' => 'conectada', 'id' => '8476238020', 'nombre' => 'Jeffry', 'usuario' => 'jeffryzeasni', 'sesion' => 'SESSION']);
        $this->post(route('admin.telegram-cv.verificar'), ['tg_clave' => 'CLAVE-PRIVADA'])->assertSessionHasNoErrors();
        $this->assertTrue($c->fresh()->lista());
        $this->assertNull($c->fresh()->codigo_hash);
        $this->assertStringNotContainsString('CLAVE-PRIVADA', json_encode(DB::table('telegram_cv_cuentas')->first()));
        Queue::assertNothingPushed();
    }

    public function test_codigo_caducado_y_desconexion_cancelan_pendientes(): void
    {
        $c = $this->conectar();
        $c->update(['estado' => 'codigo', 'codigo_en' => now()->subMinutes(11)]);
        $this->mock(TelegramCvCliente::class, fn ($m) => $m->shouldNotReceive('operar'));
        $this->post(route('admin.telegram-cv.verificar'), ['tg_codigo' => '12345'])->assertSessionHasErrors('cuenta');
        $e = $this->preparar();
        $e->update(['estado' => 'en_cola']);
        $this->mock(TelegramCvCliente::class, fn ($m) => $m->shouldReceive('operar')->once()->withArgs(fn ($c, $a) => $a === 'desconectar')->andReturn(['ok' => true]));
        $this->post(route('admin.telegram-cv.desconectar'), ['confirmar' => 1])->assertRedirect();
        $this->assertFalse($c->fresh()->lista());
        $this->assertNull($c->fresh()->sesion);
        $this->assertNull($c->fresh()->api_hash);
        $this->assertSame('fallido', $e->fresh()->estado);
    }

    public function test_estado_interrumpido_no_se_reintenta_y_solo_borrador_se_elimina(): void
    {
        $e = $this->preparar();
        $e->update(['estado' => 'enviando', 'iniciado_en' => now()->subMinutes(6)]);
        $this->getJson(route('admin.telegram-cv.estado', $e))->assertOk()->assertJsonPath('estado', 'incierto');
        $this->delete(route('admin.telegram-cv.eliminar', $e))->assertStatus(409);
        $e->update(['estado' => 'borrador']);
        $this->delete(route('admin.telegram-cv.eliminar', $e))->assertRedirect();
        $this->assertSame(0, Envio::count());
        Storage::disk('local')->assertMissing($e->cv_ruta);
    }
}
