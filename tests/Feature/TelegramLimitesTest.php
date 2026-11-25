<?php

namespace Tests\Feature;

use App\Http\Middleware\AceptaTerminos;
use App\Http\Middleware\CorreoVerificado;
use App\Models\TelegramCvAcceso;
use App\Models\TelegramCvCuenta;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Tests\TestCase;

class TelegramLimitesTest extends TestCase
{
    use RefreshDatabase;

    private User $u;

    private TelegramCvAcceso $a;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        $this->withoutMiddleware([CorreoVerificado::class, AceptaTerminos::class]);
        $this->u = User::create(['usuario' => 'limites_tg', 'name' => 'Administrador', 'email' => 'limites@example.test', 'rol' => 'admin', 'activo' => true, 'password' => 'Prueba123456']);
        $this->actingAs($this->u);
        $c = TelegramCvCuenta::sistema();
        $this->a = new TelegramCvAcceso(['telegram_cuenta_id' => $c->id, 'user_id' => $this->u->id, 'revision' => $c->revision, 'estado' => 'en_cola', 'expira_en' => now()->addMinutes(5)]);
        $this->a->id = (string) Str::uuid();
        $this->a->save();
    }

    public function test_consultar_qr_no_consume_preparacion_confirmacion_ni_login(): void
    {
        for ($i = 0; $i < 45; $i++) {
            $this->getJson(route('admin.telegram-cv.qr.estado', $this->a))->assertOk();
        }
        $this->post(route('admin.telegram-cv.preparar'), [])->assertRedirect()->assertSessionHasErrors('cuenta');
        $this->post(route('admin.telegram-cv.confirmar', (string) Str::uuid()), ['confirmar' => 1])->assertNotFound();
        $this->post(route('admin.telegram-cv.codigo'), [])->assertRedirect()->assertSessionHasErrors('cuenta');
        Queue::assertNothingPushed();
    }

    public function test_limite_de_preparacion_sigue_activo_y_no_bloquea_qr_o_confirmacion(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->post(route('admin.telegram-cv.preparar'), [])->assertRedirect();
        }
        $this->post(route('admin.telegram-cv.preparar'), [])->assertStatus(429);
        $this->getJson(route('admin.telegram-cv.qr.estado', $this->a))->assertOk();
        $this->post(route('admin.telegram-cv.confirmar', (string) Str::uuid()), ['confirmar' => 1])->assertNotFound();
    }

    public function test_contador_anterior_bloqueado_no_afecta_los_nuevos_contadores(): void
    {
        for ($i = 0; $i < 100; $i++) {
            RateLimiter::hit(sha1((string) $this->u->id), 600);
        }
        $this->assertTrue(RateLimiter::tooManyAttempts(sha1((string) $this->u->id), 5));
        $this->post(route('admin.telegram-cv.preparar'), [])->assertRedirect()->assertSessionHasErrors('cuenta');
        $this->getJson(route('admin.telegram-cv.qr.estado', $this->a))->assertOk();
    }
}
