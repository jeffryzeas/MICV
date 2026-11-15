<?php

namespace Tests\Feature;

use App\Http\Middleware\RequiereInstalacion;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class SoloWebTest extends TestCase
{
    public function test_las_rutas_retiradas_ya_no_aceptan_solicitudes(): void
    {
        $this->get('/admin/escritorio')->assertNotFound();
        $this->postJson('/api/desktop/v1/access')->assertNotFound();
        $this->postJson('/desktop/session')->assertNotFound();
        $this->assertTrue(Route::has('login'));
        $this->assertTrue(Route::has('clientes.create'));
        $this->assertTrue(Route::has('pos.pendientes.index'));
    }

    public function test_una_sesion_antigua_de_escritorio_requiere_inicio_de_sesion_web(): void
    {
        $this->withoutMiddleware(RequiereInstalacion::class);
        Route::get('/prueba-sesion', fn () => response()->noContent())->middleware('web');
        $user = new User(['rol' => 'admin']);
        $this->actingAs($user)->get('/prueba-sesion')->assertNoContent();

        $this->withSession(['desktop_device_id' => 1])->getJson('/prueba-sesion')
            ->assertUnauthorized()
            ->assertSessionMissing('desktop_device_id');
        $this->assertGuest();
    }
}
