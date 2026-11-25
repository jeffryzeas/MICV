<?php

namespace Tests\Feature;

use App\Models\TelegramCvCuenta as Cuenta;
use App\Models\TelegramCvEnvio as Envio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Str;
use Tests\TestCase;

class TelegramSistemaMigracionTest extends TestCase
{
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate:fresh')->assertSuccessful();
        $this->beforeApplicationDestroyed(function () {
            RefreshDatabaseState::$migrated = false;
        });
        $this->admin = User::create(['usuario' => 'MigracionAdmin', 'name' => 'Admin', 'email' => 'migracion@example.test', 'rol' => 'admin', 'activo' => true, 'password' => 'Prueba123456']);
        Cuenta::sistema();
    }

    public function test_migracion_conserva_credenciales_existentes_y_cancela_pendientes_anteriores(): void
    {
        $m = require database_path('migrations/2026_11_25_000011_telegram_sistema_y_qr.php');
        $m->down();
        $legado = Cuenta::create(['user_id' => $this->admin->id, 'api_id' => 12345, 'api_hash' => str_repeat('b', 32), 'telefono' => '+50688887777', 'sesion' => str_repeat('S', 100), 'estado' => 'conectada', 'telegram_id' => '8476238020', 'revision' => (string) Str::uuid()]);
        $e = new Envio(['user_id' => $this->admin->id, 'telegram_cuenta_id' => $legado->id, 'cuenta_revision' => $legado->revision, 'remitente_id' => '8476238020', 'remitente' => '+50688887777', 'telefono' => '+50588887777', 'destinatario' => 'Prueba', 'destinatario_id' => '70001', 'peer' => ['id' => '70001', 'access_hash' => '99'], 'tipo' => 'mensaje', 'mensaje' => 'Pendiente anterior', 'random_id' => '900001', 'estado' => 'en_cola']);
        $e->id = (string) Str::uuid();
        $e->save();
        $m->up();
        $this->assertSame($legado->id, Cuenta::sistema()->id);
        $this->assertNull(Cuenta::sistema()->user_id);
        $this->assertSame(str_repeat('b', 32), Cuenta::sistema()->api_hash);
        $this->assertSame(str_repeat('S', 100), Cuenta::sistema()->sesion);
        $this->assertSame('fallido', $e->fresh()->estado);
    }
}
