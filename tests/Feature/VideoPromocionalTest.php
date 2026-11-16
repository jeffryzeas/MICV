<?php

namespace Tests\Feature;

use App\Jobs\GenerarVideoPromocional;
use App\Models\Configuracion;
use App\Models\User;
use App\Services\VideoPromocional;
use App\Services\VideosPromocionales;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class VideoPromocionalTest extends TestCase
{
    use RefreshDatabase;

    public function test_programa_una_sola_generacion_por_administrador(): void
    {
        $carpeta = storage_path('framework/testing/'.Str::uuid());
        $this->app->useStoragePath($carpeta);

        try {
            Queue::fake();
            Configuracion::actual()->update(['registro_abierto' => true]);
            $admin = $this->cuenta('ADMIN-VIDEO', 'admin');

            $ruta = route('admin.video-promocional.index');
            $this->actingAs($admin)->get($ruta)->assertOk()->assertSee('Generar video');
            $this->post(route('admin.video-promocional.store'))->assertRedirect($ruta);
            $this->post(route('admin.video-promocional.store'))->assertRedirect($ruta);

            Queue::assertPushedOn(VideoPromocional::COLA, GenerarVideoPromocional::class);
            Queue::assertPushed(GenerarVideoPromocional::class, 1);
            $estado = app(VideosPromocionales::class)->ultimo($admin->id);
            $this->getJson(route('admin.video-promocional.estado', $estado['id']))
                ->assertOk()->assertJsonPath('estado', 'pendiente');
            $this->get(route('admin.video-promocional.archivo', $estado['id']))->assertNotFound();
        } finally {
            File::deleteDirectory($carpeta);
        }
    }

    public function test_protege_el_modulo_y_el_archivo_de_otras_cuentas(): void
    {
        $carpeta = storage_path('framework/testing/'.Str::uuid());
        $this->app->useStoragePath($carpeta);

        try {
            $propietario = $this->cuenta('ADMIN-UNO', 'admin');
            $otro = $this->cuenta('ADMIN-DOS', 'admin');
            $cliente = $this->cuenta('CLIENTE-VIDEO', 'cliente');
            $cliente->forceFill(['email_verified_at' => now(), 'terminos_version' => '1.0'])->save();
            $id = (string) Str::uuid();
            $videos = app(VideosPromocionales::class);
            $videos->actualizar($propietario->id, $id, 'listo');
            File::put($videos->carpeta($propietario->id, $id).'/video.mp4', 'fixture');

            $descarga = route('admin.video-promocional.archivo', [$id, 'descargar' => 1]);
            $this->actingAs($cliente)->get(route('admin.video-promocional.index'))->assertForbidden();
            $this->actingAs($otro)->get($descarga)->assertNotFound();
            $this->actingAs($propietario)->get($descarga)->assertOk()->assertDownload('Mi-CV-Tutorial.mp4');
        } finally {
            File::deleteDirectory($carpeta);
        }
    }

    private function cuenta(string $usuario, string $rol): User
    {
        return User::create([
            'usuario' => $usuario,
            'name' => $usuario,
            'email' => strtolower($usuario).'@example.com',
            'password' => 'UnaClaveCompleja123',
            'rol' => $rol,
            'activo' => true,
        ]);
    }
}
