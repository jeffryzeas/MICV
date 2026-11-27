<?php

namespace Tests\Feature;

use App\Http\Middleware\AceptaTerminos;
use App\Http\Middleware\CorreoVerificado;
use App\Jobs\EnviarPostulacion;
use App\Jobs\EnviarTelegramCv;
use App\Models\Cliente;
use App\Models\GmailCuenta;
use App\Models\Postulacion;
use App\Models\TelegramCvCuenta;
use App\Models\TelegramCvEnvio;
use App\Models\User;
use App\Services\CvEnvioRetencion;
use App\Services\GmailPostulaciones;
use App\Services\PostulacionPdf;
use App\Services\TelegramCvCliente;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class CvEnvioRetencionTest extends TestCase
{
    use RefreshDatabase;

    private User $u;

    private Cliente $cliente;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Queue::fake();
        $this->withoutMiddleware([CorreoVerificado::class, AceptaTerminos::class]);
        $this->u = User::create(['usuario' => 'CV_retencion', 'name' => 'Administrador', 'email' => 'cv@example.test', 'rol' => 'admin', 'activo' => true, 'password' => 'Prueba123456']);
        $this->actingAs($this->u);
        $this->cliente = Cliente::create(['user_id' => $this->u->id, 'primer_nombre' => 'Ana', 'primer_apellido' => 'Original', 'activo' => true]);
    }

    private function registro(string $canal, string $estado = 'enviado', array $extra = []): Postulacion|TelegramCvEnvio
    {
        $id = (string) Str::uuid();
        $op = ['diseno' => 'pro', 'paleta' => 'marino', 'idioma' => 'es', 'foto' => true];
        $base = ['user_id' => $this->u->id, 'cliente_id' => $this->cliente->id, 'cuenta_revision' => (string) Str::uuid(), 'candidato' => 'Ana Original', 'destinatario' => 'Destino', 'mensaje' => 'Saludos cordiales.', 'cv_ruta' => ($canal === 'gmail' ? 'postulaciones/' : 'telegram-cv/envios/').$id.'/cv.pdf', 'cv_nombre' => 'CV_Ana.pdf', 'cv_bytes' => 18, 'cv_sha256' => hash('sha256', '%PDF-1.4 original'), 'cv_opciones' => $op, 'estado' => $estado];
        if ($canal === 'gmail') {
            $g = GmailCuenta::firstOrCreate(['user_id' => $this->u->id], ['correo' => 'cuenta@gmail.com', 'nombre' => 'Administración', 'clave' => 'abcdefghijklmnop', 'revision' => (string) Str::uuid(), 'activo' => true, 'verificada_en' => now()]);
            $r = new Postulacion(array_replace($base, ['gmail_cuenta_id' => $g->id, 'cuenta_revision' => $g->revision, 'confirmado_por' => $this->u->id, 'creado_por' => $this->u->id, 'remitente' => $g->correo, 'remitente_nombre' => 'Administración', 'empresa' => 'Empresa', 'asunto' => 'CV', 'correo_html' => '<p>Saludos</p>'], $extra));
        } else {
            $c = TelegramCvCuenta::sistema();
            $c->update(['estado' => 'conectada', 'telegram_id' => '8476238020', 'telefono' => '+50688887777', 'sesion' => str_repeat('S', 100)]);
            $r = new TelegramCvEnvio(array_replace($base, ['telegram_cuenta_id' => $c->id, 'cuenta_revision' => $c->revision, 'remitente_id' => $c->telegram_id, 'remitente' => $c->telefono, 'telefono' => '+50588887777', 'destinatario_id' => '700001', 'peer' => ['id' => '700001', 'access_hash' => '-90001'], 'tipo' => 'cv', 'random_id' => (string) random_int(1, PHP_INT_MAX)], $extra));
        }
        $r->id = $id;
        $r->save();
        Storage::disk('local')->put($r->cv_ruta, '%PDF-1.4 original');

        return $r;
    }

    public function test_limpieza_de_enviados_retira_solo_pdf_y_conserva_registros_disenos_y_originales(): void
    {
        $p = $this->registro('gmail');
        $e = $this->registro('telegram');
        $b = $this->registro('gmail', 'borrador');
        Storage::disk('local')->put('clientes/foto.jpg', 'foto original');
        Storage::disk('local')->put('documentos/original.pdf', 'documento');
        Storage::disk('local')->put('tiktok/avatares/original.jpg', 'captura');
        $this->assertSame(2, app(CvEnvioRetencion::class)->limpiar());
        Storage::disk('local')->assertMissing($p->cv_ruta);
        Storage::disk('local')->assertMissing($e->cv_ruta);
        foreach ([$b->cv_ruta, 'clientes/foto.jpg', 'documentos/original.pdf', 'tiktok/avatares/original.jpg'] as $r) {
            Storage::disk('local')->assertExists($r);
        }
        $this->assertSame(2, Postulacion::count());
        $this->assertSame(1, TelegramCvEnvio::count());
        $this->assertSame('pro', $p->fresh()->cv_opciones['diseno']);
        $this->assertSame('enviado', $e->fresh()->estado);
        $this->assertNotNull($p->fresh()->cv_eliminado_en);
        $this->assertSame(0, app(CvEnvioRetencion::class)->limpiar());
    }

    public function test_correo_usa_adjunto_durante_envio_y_lo_retira_tras_confirmacion(): void
    {
        $p = $this->registro('gmail', 'en_cola');
        $mock = $this->mock(GmailPostulaciones::class, fn ($m) => $m->shouldReceive('enviar')->once()->andReturnUsing(function ($registro) {
            Storage::disk('local')->assertExists($registro->cv_ruta);
            $this->assertSame('%PDF-1.4 original', Storage::disk('local')->get($registro->cv_ruta));

            return 'message-id';
        }));
        (new EnviarPostulacion($p->id))->handle($mock);
        $this->assertSame('enviado', $p->fresh()->estado);
        $this->assertSame('message-id', $p->fresh()->message_id);
        Storage::disk('local')->assertMissing($p->cv_ruta);
    }

    public function test_telegram_usa_pdf_durante_carga_y_lo_retira_tras_aceptacion(): void
    {
        $e = $this->registro('telegram', 'en_cola');
        $mock = $this->mock(TelegramCvCliente::class, fn ($m) => $m->shouldReceive('operar')->once()->andReturnUsing(function ($c, $a, $d) use ($e) {
            $this->assertSame('enviar', $a);
            $this->assertFileExists($d['archivo']);
            $this->assertSame($e->cv_sha256, hash_file('sha256', $d['archivo']));

            return ['mensaje_id' => '99'];
        }));
        (new EnviarTelegramCv($e->id))->handle($mock);
        $this->assertSame('enviado', $e->fresh()->estado);
        Storage::disk('local')->assertMissing($e->cv_ruta);
        $this->assertNotNull($e->fresh()->cv_eliminado_en);
    }

    public function test_regeneracion_usa_datos_actuales_y_opciones_guardadas_sin_archivos_persistentes(): void
    {
        $p = $this->registro('gmail');
        $e = $this->registro('telegram');
        app(CvEnvioRetencion::class)->limpiar();
        $this->cliente->update(['primer_apellido' => 'Actual']);
        $mock = $this->mock(PostulacionPdf::class, fn ($m) => $m->shouldReceive('generar')->twice()->withArgs(fn ($c, $u, $op) => $c->nombreCompleto() === 'Ana Actual' && $u->id === $this->u->id && $op['diseno'] === 'pro' && $op['paleta'] === 'marino')->andReturn('%PDF-1.4 datos actuales'));
        $this->get(route('postulaciones.pdf', ['id' => $p->id, 'descargar' => 1]))->assertOk()->assertHeader('X-MiCV-CV', 'regenerado-datos-actuales')->assertSee('%PDF-1.4 datos actuales', false);
        $this->get(route('admin.telegram-cv.pdf', $e))->assertOk()->assertHeader('X-MiCV-CV', 'regenerado-datos-actuales');
        $this->assertSame([], Storage::disk('local')->allFiles());
        $this->assertSame(hash('sha256', '%PDF-1.4 original'), $p->fresh()->cv_sha256);
    }

    public function test_caducan_borradores_antiguos_y_no_las_colas_recientes_ni_el_envio_activo(): void
    {
        $viejo = $this->registro('gmail', 'borrador', ['created_at' => now()->subHours(25)]);
        $cola = $this->registro('telegram', 'en_cola', ['created_at' => now()->subHours(26), 'encolado_en' => now()]);
        $activo = $this->registro('gmail', 'enviando', ['iniciado_en' => now()->subMinutes(5)]);
        $roto = $this->registro('telegram', 'enviando', ['iniciado_en' => now()->subMinutes(16)]);
        $this->assertSame(2, app(CvEnvioRetencion::class)->limpiar());
        $this->assertSame('caducado', $viejo->fresh()->estado);
        $this->assertSame('incierto', $roto->fresh()->estado);
        Storage::disk('local')->assertMissing($viejo->cv_ruta);
        Storage::disk('local')->assertMissing($roto->cv_ruta);
        Storage::disk('local')->assertExists($cola->cv_ruta);
        Storage::disk('local')->assertExists($activo->cv_ruta);
    }

    public function test_pdf_pendiente_perdido_no_se_regenera_silenciosamente_y_ruta_alterada_no_borra_otro_archivo(): void
    {
        $p = $this->registro('gmail', 'borrador');
        Storage::disk('local')->delete($p->cv_ruta);
        $this->mock(PostulacionPdf::class, fn ($m) => $m->shouldNotReceive('generar'));
        $this->get(route('postulaciones.pdf', $p))->assertStatus(410);
        $otro = $this->registro('gmail', 'enviado', ['cv_ruta' => 'clientes/foto.jpg']);
        $this->assertFalse(app(CvEnvioRetencion::class)->borrar($otro));
        Storage::disk('local')->assertExists('clientes/foto.jpg');
    }

    public function test_registro_de_cliente_no_permite_regenerar_perfil_ajeno(): void
    {
        $p = $this->registro('gmail');
        $u = User::create(['usuario' => 'CV_otro', 'name' => 'Otro', 'email' => 'otro-cv@example.test', 'rol' => 'cliente', 'activo' => true, 'password' => 'Prueba123456']);
        $this->actingAs($u)->get(route('postulaciones.pdf',$p))->assertNotFound();
    }
}
