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
            File::put($videos->carpeta($propietario->id, $id).'/estado.json', json_encode(['id' => $id, 'estado' => 'listo', 'creado' => now()->getTimestamp(), 'actualizado' => now()->getTimestamp()]));
            $this->assertSame(100, $videos->estado($propietario->id, $id)['porcentaje']);
            File::put($videos->carpeta($propietario->id, $id).'/video.mp4', 'fixture');

            $descarga = route('admin.video-promocional.archivo', [$id, 'descargar' => 1]);
            $this->actingAs($cliente)->get(route('admin.video-promocional.index'))->assertForbidden();
            $this->actingAs($otro)->get($descarga)->assertNotFound();
            $this->actingAs($propietario)->get($descarga)->assertOk()->assertDownload('Mi-CV-Tutorial.mp4');
        } finally {
            File::deleteDirectory($carpeta);
        }
    }

    public function test_publica_progreso_real_sin_permitir_que_sobrescriba_el_estado_final(): void
    {
        $carpeta = storage_path('framework/testing/'.Str::uuid());
        $this->app->useStoragePath($carpeta);
        try {
            $admin = $this->cuenta('ADMIN-PROGRESO', 'admin');
            $videos = app(VideosPromocionales::class);
            $id = (string) Str::uuid();
            $videos->actualizar($admin->id, $id, 'pendiente');
            $this->travel(3)->seconds();
            $inicio = $videos->actualizar($admin->id, $id, 'procesando');
            $this->assertNotNull($inicio['iniciado']);
            File::put($videos->carpeta($admin->id, $id).'/progreso.json', json_encode([
                'etapa' => 'render', 'porcentaje' => 62,
                'mensaje' => 'Animando escena 5 de 10.', 'escena' => 5, 'escenas' => 10,
                'actualizado' => now()->getTimestamp() + 2, 'motor_voz' => 'piper',
                'estado' => 'listo', 'ruta_interna' => '/privado',
            ]));
            $this->actingAs($admin)->getJson(route('admin.video-promocional.estado', $id))
                ->assertOk()->assertJsonPath('estado', 'procesando')
                ->assertJsonPath('porcentaje', 62)->assertJsonPath('etapa', 'render')
                ->assertJsonPath('motor_voz', 'piper')->assertJsonMissingPath('ruta_interna');
            $fin = $videos->actualizar($admin->id, $id, 'listo');
            $this->assertSame(100, $fin['porcentaje']);
            $this->assertSame($inicio['iniciado'], $fin['iniciado']);
            $this->assertSame('completado', $videos->estado($admin->id, $id)['etapa']);
            $this->actingAs($admin)->get(route('admin.video-promocional.index'))
                ->assertOk()->assertSee('video-progreso')->assertSee('100%');
        } finally {
            File::deleteDirectory($carpeta);
        }
    }

    public function test_tolera_progreso_invalido_y_limita_el_porcentaje_hasta_la_publicacion(): void
    {
        $carpeta = storage_path('framework/testing/'.Str::uuid());
        $this->app->useStoragePath($carpeta);
        try {
            $videos = app(VideosPromocionales::class);
            $id = (string) Str::uuid();
            $videos->actualizar(1, $id, 'procesando');
            $ruta = $videos->carpeta(1, $id).'/progreso.json';
            File::put($ruta, '{');
            $this->assertSame(1, $videos->estado(1, $id)['porcentaje']);
            File::put($ruta, '{"porcentaje":150,"etapa":"exportacion"}');
            $this->assertSame(99, $videos->estado(1, $id)['porcentaje']);
            $error = $videos->actualizar(1, $id, 'error');
            $this->assertSame(99, $error['porcentaje']);
            $this->assertSame('error', $videos->estado(1, $id)['estado']);
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
