<?php

namespace Tests\Feature;

use App\Http\Middleware\AceptaTerminos;
use App\Http\Middleware\CorreoVerificado;
use App\Jobs\ConectarTelegramQr;
use App\Jobs\EnviarTelegramCv;
use App\Models\Cliente;
use App\Models\TelegramCvAcceso as Acceso;
use App\Models\TelegramCvCuenta as Cuenta;
use App\Models\TelegramCvEnvio as Envio;
use App\Models\User;
use App\Services\PostulacionPdf;
use App\Services\TelegramCvCliente;
use App\Services\TelegramCvQr;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class TelegramSistemaQrTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Cuenta $c;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Queue::fake();
        $this->withoutMiddleware([CorreoVerificado::class, AceptaTerminos::class]);
        $this->admin = $this->usuario('admin');
        $this->actingAs($this->admin);
        $this->c = Cuenta::sistema();
        config(['telegram_cv.directorio' => Storage::disk('local')->path('locks')]);
    }

    private function usuario(string $rol = 'cliente'): User
    {
        $x = Str::random(8);

        return User::create(['usuario' => 'QR'.$x, 'name' => 'Nombre '.$x, 'email' => $x.'@example.test', 'rol' => $rol, 'activo' => true, 'password' => 'Prueba123456']);
    }

    private function solicitud(): Acceso
    {
        $this->post(route('admin.telegram-cv.qr.crear'), ['tg_api_id' => 12345, 'tg_api_hash' => str_repeat('a', 32), 'confirmar' => 1])->assertSessionHasNoErrors()->assertRedirect();

        return Acceso::latest()->firstOrFail();
    }

    private function qr(): array
    {
        return ['v' => 1, 'tipo' => 'qr', 'url' => 'tg://login?token='.str_repeat('A', 43), 'expira_ms' => now()->addSeconds(50)->getTimestampMs()];
    }

    private function listo(): array
    {
        return ['v' => 1, 'tipo' => 'conectada', 'sesion' => str_repeat('S', 100), 'id' => '8476238020', 'telefono' => '+50688887777', 'nombre' => 'Telegram del sistema', 'usuario' => 'jeffryzeasni'];
    }

    public function test_qr_se_encola_una_vez_y_no_incluye_credenciales_en_respuesta(): void
    {
        $a = $this->solicitud();
        $this->post(route('admin.telegram-cv.qr.crear'), ['tg_api_id' => 12345, 'confirmar' => 1])->assertRedirect();
        Queue::assertPushed(ConectarTelegramQr::class, 1);
        $this->assertSame(1, Acceso::count());
        $this->get(route('admin.telegram-cv.cuenta'))->assertOk()->assertSee('Vincular dispositivo')->assertDontSee(str_repeat('a', 32), false);
        $this->getJson(route('admin.telegram-cv.qr.estado', $a))->assertOk()->assertJsonPath('qr_url', null);
    }

    public function test_token_cifrado_privado_y_cancelacion_lo_retira(): void
    {
        $a = $this->solicitud();
        $s = app(TelegramCvQr::class);
        $this->assertTrue($s->recibir($a->id, $this->qr()));
        $this->assertNotSame($this->qr()['url'], DB::table('telegram_cv_accesos')->value('qr_url'));
        $this->assertArrayNotHasKey('qr_url', $a->fresh()->toArray());
        $this->getJson(route('admin.telegram-cv.qr.estado', $a))->assertHeader('Cache-Control', 'max-age=0, no-store, private')->assertJsonPath('qr_url', $this->qr()['url']);
        $this->post(route('admin.telegram-cv.qr.cancelar', $a), ['confirmar' => 1])->assertRedirect();
        $this->getJson(route('admin.telegram-cv.qr.estado', $a))->assertJsonPath('estado', 'cancelada')->assertJsonPath('qr_url', null);
        $this->assertFalse($s->recibir($a->id, $this->listo()));
        $this->assertNull($this->c->fresh()->sesion);
    }

    public function test_vence_token_y_solicitud_sin_aceptar_resultados_tardios(): void
    {
        $a = $this->solicitud();
        $s = app(TelegramCvQr::class);
        $s->recibir($a->id, $this->qr());
        $this->travel(51)->seconds();$a->update(['latido_en'=>now()]);
        $this->assertTrue($a->fresh()->qr_expira_en->isPast(), json_encode(['vence' => (string) $a->fresh()->qr_expira_en, 'ahora' => (string) now()]));
        $this->getJson(route('admin.telegram-cv.qr.estado', $a))->assertJsonPath('qr_url', null);
        $this->travel(5)->minutes();
        $this->getJson(route('admin.telegram-cv.qr.estado', $a))->assertJsonPath('estado', 'caducada');
        $this->assertFalse($s->recibir($a->id, $this->listo()));
    }

    public function test_conexion_aprobada_queda_global_y_no_se_borra_al_eliminar_administrador(): void
    {
        $a = $this->solicitud();
        $this->assertTrue(app(TelegramCvQr::class)->recibir($a->id, $this->listo()));
        $this->assertTrue(Cuenta::sistema()->lista());
        $this->assertSame('+50688887777', Cuenta::sistema()->telefono);
        $this->assertNull($a->fresh()->qr_url);
        $this->assertNotSame(str_repeat('S', 100), DB::table('telegram_cv_cuentas')->value('sesion'));
        $id = $this->c->id;
        $this->admin->delete();
        $this->assertSame($id, Cuenta::sistema()->id);
        $this->assertTrue(Cuenta::sistema()->lista());
    }

    public function test_qr_con_dos_pasos_exige_contrasena_sin_pedir_codigo(): void
    {
        $a = $this->solicitud();
        $this->assertTrue(app(TelegramCvQr::class)->recibir($a->id, ['v' => 1, 'tipo' => 'clave', 'sesion' => str_repeat('S', 100)]));
        $this->get(route('admin.telegram-cv.cuenta'))->assertOk()->assertSee('QR aprobado')->assertSee('tg_clave', false)->assertDontSee('tg_codigo', false);
        $this->mock(TelegramCvCliente::class, fn ($m) => $m->shouldReceive('operar')->once()->withArgs(fn ($c, $a, $d) => $a === 'clave' && $c->codigo_hash === 'qr')->andReturn(['estado' => 'conectada', 'id' => '8476238020', 'telefono' => '+50688887777', 'nombre' => 'Sistema', 'sesion' => str_repeat('S', 100)]));
        $this->post(route('admin.telegram-cv.verificar'), ['tg_clave' => 'CLAVE-FICTICIA'])->assertSessionHasNoErrors();
        $this->assertTrue(Cuenta::sistema()->lista());
        $this->assertSame('+50688887777', Cuenta::sistema()->telefono);
    }

    public function test_cliente_solo_indica_numero_no_puede_administrar_cuenta_y_envia_su_cv(): void
    {
        $a = $this->solicitud();
        app(TelegramCvQr::class)->recibir($a->id, $this->listo());
        $u = $this->usuario();
        $persona = Cliente::create(['user_id' => $u->id, 'primer_nombre' => 'Ana', 'primer_apellido' => 'Pérez', 'activo' => true]);
        $this->actingAs($u);
        $this->get(route('admin.telegram-cv.crear'))->assertOk()->assertSee('Número que recibirá el CV')->assertDontSee('tg_api_hash', false)->assertDontSee('Conectar mi número');
        $this->get(route('admin.telegram-cv.cuenta'))->assertForbidden();
        $this->getJson(route('admin.telegram-cv.qr.estado', $a))->assertForbidden();
        $this->post(route('admin.telegram-cv.qr.crear'), [])->assertForbidden();
        $this->mock(PostulacionPdf::class, fn ($m) => $m->shouldReceive('generar')->once()->withArgs(fn ($c, $actor) => $c->id === $persona->id && $actor->id === $u->id)->andReturn('%PDF-1.4 copia'));
        $this->mock(TelegramCvCliente::class, fn ($m) => $m->shouldReceive('operar')->once()->andReturn(['id' => '700001', 'access_hash' => '-900001', 'nombre' => 'Destinatario real']));
        $this->post(route('admin.telegram-cv.preparar'), ['telefono' => '+50588887777', 'confirmar_contacto' => 1, 'tipo' => 'mensaje', 'mensaje' => 'Texto que debe ignorarse'])->assertSessionHasNoErrors()->assertRedirect();
        $e = Envio::latest()->firstOrFail();
        $this->assertSame('cv', $e->tipo);
        $this->assertSame($u->id, $e->user_id);
        $this->assertSame($this->c->id, $e->telegram_cuenta_id);
        $this->assertStringNotContainsString('ignorarse', $e->mensaje);
        $this->post(route('admin.telegram-cv.confirmar', $e), ['confirmar' => 1])->assertRedirect();
        Queue::assertPushed(EnviarTelegramCv::class, 1);
        $s = new class extends TelegramCvCliente
        {
            public function operar(Cuenta $c, string $a, array $d = []): array
            {
                return ['mensaje_id' => '99'];
            }
        };
        (new EnviarTelegramCv($e->id))->handle($s);
        $this->assertSame('enviado', $e->fresh()->estado);
    }

    public function test_no_puede_usar_cv_ajeno_ni_ver_historial_de_otros(): void
    {
        $a = $this->solicitud();
        app(TelegramCvQr::class)->recibir($a->id, $this->listo());
        $u = $this->usuario();
        $otro = $this->usuario();
        $c = Cliente::create(['user_id' => $otro->id, 'primer_nombre' => 'Ajeno', 'primer_apellido' => 'Privado', 'activo' => true]);
        $this->actingAs($u);
        $this->post(route('admin.telegram-cv.preparar'), ['cliente_id' => $c->id, 'telefono' => '+50588887777', 'confirmar_contacto' => 1])->assertNotFound();
        $this->assertSame(0, Envio::count());
    }

    public function test_worker_real_intercambia_eventos_y_aceptacion_por_pipe_sin_telegram(): void
    {
        $a = $this->solicitud();
        config(['telegram_cv.python' => PHP_BINARY]);
        $fixture = Storage::disk('local')->path('qr-fixture.php');
        $programa = '<?php $d=json_decode(fgets(STDIN),true); echo json_encode('.var_export($this->qr(), true).')."\n"; fflush(STDOUT); echo json_encode('.var_export($this->listo(), true).')."\n"; fflush(STDOUT); $ack=json_decode(fgets(STDIN),true); if(($ack["accion"]??"")!=="aceptar")exit(3);';
        file_put_contents($fixture, $programa);
        $s = new class($fixture) extends TelegramCvQr
        {
            public function __construct(private string $fixture) {}

            protected function proceso(InputStream $i): Process
            {
                return (new Process([PHP_BINARY, $this->fixture]))->setInput($i);
            }
        };
        $s->ejecutar($a->id);
        $this->assertSame('conectada',$a->fresh()->estado);
        $this->assertTrue(Cuenta::sistema()->lista());
    }
}
