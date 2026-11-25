<?php

namespace Tests\Feature;

use App\Models\TelegramCvCuenta as Cuenta;
use App\Models\User;
use App\Services\TelegramCvCliente;
use App\Services\TelegramCvError;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class TelegramCvClienteTest extends TestCase
{
    use RefreshDatabase;

    private Cuenta $c;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        config(['telegram_cv.directorio' => Storage::disk('local')->path('telegram-cv-locks')]);
        $u = User::create(['usuario' => 'TC_transport', 'name' => 'Admin', 'email' => 'transport@example.test', 'rol' => 'admin', 'activo' => true, 'password' => 'Prueba123456']);
        $this->c = Cuenta::para($u);
        $this->c->update(['api_id' => 12345, 'api_hash' => str_repeat('b', 32), 'telefono' => '+50688887777', 'sesion' => 'SESION-ANTERIOR', 'estado' => 'conectada', 'telegram_id' => '8476238020']);
    }

    private function servicio(callable $f): TelegramCvCliente
    {
        return new class($f) extends TelegramCvCliente
        {
            public function __construct(private $f) {}

            protected function proceso(array $d, int $t): array
            {
                return ($this->f)($d, $t);
            }
        };
    }

    public function test_proceso_informa_python_ausente_sin_exponer_entrada(): void
    {
        config(['telegram_cv.python' => Storage::disk('local')->path('python-inexistente')]);
        $s = new class extends TelegramCvCliente
        {
            public function probar(): array
            {
                return parent::proceso(['api_hash' => 'SECRETO-PROCESO', 'sesion' => 'SESION-PRIVADA'], 1);
            }
        };
        try {
            $s->probar();
            $this->fail();
        } catch (TelegramCvError $e) {
            $this->assertSame('PYTHON_NO_DISPONIBLE', $e->diagnostico);
            $this->assertStringNotContainsString('SECRETO-PROCESO', $e->getMessage());
            $this->assertStringNotContainsString('SESION-PRIVADA', $e->getMessage());
        }
    }

    public function test_diagnostico_desconocido_no_filtra_descripcion_y_conexion_se_distingue(): void
    {
        $s = $this->servicio(fn () => ['v' => 1, 'ok' => false, 'error' => 'conexion', 'diagnostico' => 'CONEXION', 'descripcion' => 'SECRETO-REMOTO']);
        try {
            $s->operar($this->c, 'codigo');
            $this->fail();
        } catch (TelegramCvError $e) {
            $this->assertSame('conexion', $e->motivo);
            $this->assertSame('CONEXION', $e->diagnostico);
            $this->assertStringNotContainsString('SECRETO-REMOTO', $e->getMessage());
        }
        $s = $this->servicio(fn () => ['v' => 1, 'ok' => false, 'error' => 'servicio', 'diagnostico' => 'SECRETO-NO-PERMITIDO']);
        try {
            $s->operar($this->c, 'codigo');
            $this->fail();
        } catch (TelegramCvError $e) {
            $this->assertSame('', $e->diagnostico);
            $this->assertStringNotContainsString('SECRETO-NO-PERMITIDO', $e->getMessage());
        }
    }

    public function test_credenciales_solo_van_a_entrada_y_sesion_retornada_se_cifra(): void
    {
        $s = $this->servicio(function ($d, $t) {
            $this->assertSame(str_repeat('b', 32), $d['api_hash']);
            $this->assertSame('SESION-ANTERIOR', $d['sesion']);
            $this->assertSame('8476238020', $d['cuenta_id']);
            $this->assertSame(45, $t);

            return ['v' => 1, 'ok' => true, 'sesion' => 'SESION-NUEVA', 'id' => '7'];
        });
        $r = $s->operar($this->c, 'resolver', ['destino' => '+50588887777']);
        $this->assertSame('7', $r['id']);
        $this->assertSame('SESION-NUEVA', $this->c->fresh()->sesion);
        $this->assertNotSame('SESION-NUEVA', DB::table('telegram_cv_cuentas')->value('sesion'));
    }

    public function test_respuesta_desconocida_no_se_expone_y_flood_reserva_espera(): void
    {
        $s = $this->servicio(fn () => ['v' => 1, 'ok' => false, 'error' => 'secreto bbbbbbbbbbbbbbbb', 'descripcion' => 'TOKEN-SECRETO']);
        try {
            $s->operar($this->c, 'resolver');
            $this->fail();
        } catch (TelegramCvError $e) {
            $this->assertSame('servicio', $e->motivo);
            $this->assertStringNotContainsString('TOKEN-SECRETO', $e->getMessage());
        }$s = $this->servicio(fn () => ['v' => 1, 'ok' => false, 'error' => 'limite', 'espera' => 120]);
        try {
            $s->operar($this->c, 'resolver');
            $this->fail();
        } catch (TelegramCvError $e) {
            $this->assertSame(120, $e->espera);
        }$this->assertTrue($this->c->fresh()->no_antes_de->gt(now()->addSeconds(110)));
    }

    public function test_cuenta_cambiada_y_lock_ocupado_no_abren_proceso(): void
    {
        $s = $this->servicio(function () {
            $this->fail('No debe ejecutar Python.');
        });
        $vieja = $this->c->fresh();
        $this->c->update(['revision' => (string) Str::uuid()]);
        try {
            $s->operar($vieja, 'enviar');
            $this->fail();
        } catch (TelegramCvError $e) {
            $this->assertSame('sesion', $e->motivo);
        }$dir = config('telegram_cv.directorio');
        $h = fopen($dir.'/cuenta-'.$this->c->id.'.lock', 'c');
        flock($h, LOCK_EX);
        try {
            $s->operar($this->c, 'resolver');
            $this->fail();
        } catch (TelegramCvError $e) {
            $this->assertSame('ocupada', $e->motivo);
        } finally {
            flock($h, LOCK_UN);
            fclose($h);
        }
    }

    public function test_cambio_durante_proceso_no_restaura_sesion_desconectada(): void
    {
        $s = $this->servicio(function () {
            $this->c->update(['revision' => (string) Str::uuid(), 'sesion' => null, 'estado' => 'desconectada']);

            return ['v' => 1, 'ok' => true, 'sesion' => 'SESION-VIEJA'];
        });
        try {
            $s->operar($this->c, 'resolver');
            $this->fail();
        } catch (TelegramCvError $e) {
            $this->assertSame('sesion', $e->motivo);
        }$this->assertNull($this->c->fresh()->sesion);
    }

    public function test_sesion_revocada_no_permanece_lista(): void
    {
        $s = $this->servicio(fn () => ['v' => 1, 'ok' => false, 'error' => 'sesion']);
        try {
            $s->operar($this->c, 'enviar');
            $this->fail();
        } catch (TelegramCvError $e) {
            $this->assertSame('sesion', $e->motivo);
        }$this->assertFalse($this->c->fresh()->lista());
        $this->assertNull($this->c->fresh()->sesion);
    }
}
